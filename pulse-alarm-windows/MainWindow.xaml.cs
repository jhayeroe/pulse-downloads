using System.Collections.ObjectModel;
using System.Text.Json;
using System.Windows;
using System.Windows.Threading;
using Microsoft.Win32;
using PulseAlarm.Windows.Models;
using PulseAlarm.Windows.Services;
using Forms = System.Windows.Forms;

namespace PulseAlarm.Windows;

public partial class MainWindow : Window
{
    private readonly ObservableCollection<AlarmItem> _alarms;
    private readonly AlarmStore _store = new();
    private readonly PulseCloudClient _cloud = new();
    private readonly DispatcherTimer _timer = new() { Interval = TimeSpan.FromSeconds(1) };
    private readonly Forms.NotifyIcon _tray;
    private bool _allowClose;
    private bool _cloudBusy;
    private DateTime _nextCloudSync = DateTime.MinValue;

    public MainWindow()
    {
        InitializeComponent();
        _alarms = new ObservableCollection<AlarmItem>(_store.Load().OrderBy(x => x.DueAt));
        AlarmList.ItemsSource = _alarms;
        _timer.Tick += Timer_Tick;
        _timer.Start();

        _tray = new Forms.NotifyIcon
        {
            Text = "PULSE Alarm",
            Icon = System.Drawing.SystemIcons.Information,
            Visible = true
        };
        _tray.DoubleClick += (_, _) => RestoreWindow();
        var menu = new Forms.ContextMenuStrip();
        menu.Items.Add("Open PULSE", null, (_, _) => RestoreWindow());
        menu.Items.Add("Sync now", null, async (_, _) => await SyncCloudAsync(true));
        menu.Items.Add("Exit", null, (_, _) => ExitApp());
        _tray.ContextMenuStrip = menu;
        UpdateAccountUi();
        _ = SyncCloudAsync(false);
    }

    private async void Timer_Tick(object? sender, EventArgs e)
    {
        ClockText.Text = DateTime.Now.ToString("h:mm:ss tt");
        DateText.Text = DateTime.Now.ToString("dddd, MMMM d");
        foreach (var alarm in _alarms.Where(a => a.IsEnabled && !a.IsDone && !a.HasFired && a.DueAt <= DateTime.Now).ToList())
        {
            alarm.HasFired = true;
            _store.Save(_alarms);
            new AlarmWindow(alarm, MeetingToggle.IsChecked == true, SaveAndRefresh).Show();
        }
        if (_cloud.IsSignedIn && !_cloudBusy && DateTime.Now >= _nextCloudSync)
            await SyncCloudAsync(false);
    }

    private async void AddAlarm_Click(object sender, RoutedEventArgs e)
    {
        var dialog = new AddAlarmWindow { Owner = this };
        if (dialog.ShowDialog() == true && dialog.Alarm is not null)
        {
            var a = dialog.Alarm;
            a.LocalId = DateTimeOffset.UtcNow.ToUnixTimeMilliseconds();
            a.BaseHour = a.DueAt.Hour;
            a.BaseMinute = a.DueAt.Minute;
            a.LastAction = "edit";
            a.EnsureClientId();
            _alarms.Add(a);
            SaveAndRefresh();
            await SyncCloudAsync(false);
        }
    }

    private async void Done_Click(object sender, RoutedEventArgs e)
    {
        if ((sender as FrameworkElement)?.Tag is AlarmItem alarm)
        {
            alarm.IsDone = true;
            alarm.IsEnabled = false;
            alarm.LastAction = "done";
            SaveAndRefresh();
            await SyncCloudAsync(false);
        }
    }

    private void MeetingToggle_OnChanged(object sender, RoutedEventArgs e)
    {
        if (!IsLoaded) return;
        MeetingToggle.Content = MeetingToggle.IsChecked == true ? "ON" : "OFF";
    }

    private async void Account_Click(object sender, RoutedEventArgs e)
    {
        if (_cloud.IsSignedIn)
        {
            var result = System.Windows.MessageBox.Show($"Signed in as {_cloud.Email}. Sign out of Pulse Account on this desktop?", "PULSE Account", MessageBoxButton.YesNo, MessageBoxImage.Question);
            if (result == MessageBoxResult.Yes)
            {
                _cloud.SignOut();
                UpdateAccountUi();
            }
            return;
        }
        var dialog = new AccountWindow(_cloud) { Owner = this };
        if (dialog.ShowDialog() == true)
        {
            UpdateAccountUi();
            await SyncCloudAsync(true);
        }
    }

    private async void Sync_Click(object sender, RoutedEventArgs e) => await SyncCloudAsync(true);

    private async Task SyncCloudAsync(bool showError)
    {
        if (!_cloud.IsSignedIn)
        {
            if (showError) System.Windows.MessageBox.Show("Sign in to the same Pulse Account used on Android first.", "PULSE Sync");
            UpdateAccountUi();
            return;
        }
        if (_cloudBusy) return;
        _cloudBusy = true;
        try
        {
            SyncText.Text = "Syncing...";
            SyncButton.IsEnabled = false;
            var merged = await _cloud.SyncAsync(_alarms.ToList());
            _alarms.Clear();
            foreach (var a in merged.OrderBy(x => x.DueAt)) _alarms.Add(a);
            _store.Save(_alarms);
            AlarmList.Items.Refresh();
            _nextCloudSync = DateTime.Now.AddSeconds(5);
            UpdateAccountUi();
        }
        catch (Exception ex)
        {
            _nextCloudSync = DateTime.Now.AddSeconds(10);
            UpdateAccountUi();
            if (showError) System.Windows.MessageBox.Show(ex.Message, "PULSE Sync", MessageBoxButton.OK, MessageBoxImage.Warning);
        }
        finally
        {
            _cloudBusy = false;
            SyncButton.IsEnabled = true;
        }
    }

    private void UpdateAccountUi()
    {
        if (_cloud.IsSignedIn)
        {
            AccountText.Text = _cloud.Email;
            AccountButton.Content = "SIGN OUT";
            if (!string.IsNullOrWhiteSpace(_cloud.LastError)) SyncText.Text = "Sync error • " + _cloud.LastError;
            else if (_cloud.LastSyncAt is not null) SyncText.Text = $"Synced {_alarms.Count} alarms • {_cloud.LastSyncAt.Value: h:mm:ss tt}";
            else SyncText.Text = "Connected • waiting for first sync";
        }
        else
        {
            AccountText.Text = "Not signed in";
            AccountButton.Content = "SIGN IN";
            SyncText.Text = "Cloud sync is off";
        }
    }

    private void ImportBackup_Click(object sender, RoutedEventArgs e)
    {
        var dlg = new OpenFileDialog { Filter = "PULSE backup (*.json)|*.json|All files (*.*)|*.*" };
        if (dlg.ShowDialog() != true) return;
        try
        {
            var imported = ParsePulseBackup(File.ReadAllText(dlg.FileName));
            if (imported.Count == 0) throw new InvalidOperationException("No alarms found in this backup.");
            _alarms.Clear();
            foreach (var a in imported.OrderBy(x => x.DueAt)) _alarms.Add(a);
            SaveAndRefresh();
            _ = SyncCloudAsync(false);
            System.Windows.MessageBox.Show($"Imported {_alarms.Count} alarms. They will also sync to your Pulse Account when connected.", "PULSE Backup");
        }
        catch (Exception ex) { System.Windows.MessageBox.Show(ex.Message, "Import failed", MessageBoxButton.OK, MessageBoxImage.Warning); }
    }

    private void ExportBackup_Click(object sender, RoutedEventArgs e)
    {
        var dlg = new SaveFileDialog { Filter = "PULSE backup (*.pulse.json)|*.pulse.json|JSON (*.json)|*.json", FileName = $"PULSE_Desktop_Backup_{DateTime.Now:yyyy-MM-dd_HHmm}.pulse.json" };
        if (dlg.ShowDialog() != true) return;
        var root = new
        {
            format = "PULSE_BACKUP",
            schema = 1,
            createdAt = DateTimeOffset.UtcNow.ToUnixTimeMilliseconds(),
            appVersion = "windows-1.1.0",
            alarms = _alarms.Select(a => new
            {
                id = a.LocalId,
                title = a.Title,
                details = a.Details,
                hour = a.BaseHour == 0 && a.BaseMinute == 0 ? a.DueAt.Hour : a.BaseHour,
                minute = a.BaseHour == 0 && a.BaseMinute == 0 ? a.DueAt.Minute : a.BaseMinute,
                category = a.Category,
                important = a.IsImportant,
                enabled = a.IsEnabled && !a.IsDone,
                strongVibration = a.Vibrate,
                bypassSilent = a.BypassSilent,
                nextAt = new DateTimeOffset(a.DueAt).ToUnixTimeMilliseconds(),
                oneShotAt = new DateTimeOffset(a.DueAt).ToUnixTimeMilliseconds(),
                cloudClientId = a.CloudClientId,
                cloudVersion = a.CloudVersion,
                lastAction = a.LastAction,
                snoozeMinutes = a.SnoozeMinutes,
                days = a.RepeatDays
            })
        };
        File.WriteAllText(dlg.FileName, JsonSerializer.Serialize(root, new JsonSerializerOptions { WriteIndented = true }));
    }

    private static List<AlarmItem> ParsePulseBackup(string raw)
    {
        using var doc = JsonDocument.Parse(raw);
        var root = doc.RootElement;
        if (!root.TryGetProperty("alarms", out var alarms) || alarms.ValueKind != JsonValueKind.Array) throw new InvalidOperationException("Not a valid PULSE backup file.");
        var result = new List<AlarmItem>();
        foreach (var x in alarms.EnumerateArray())
        {
            var id = x.TryGetProperty("id", out var idv) && idv.TryGetInt64(out var n) ? n : DateTimeOffset.UtcNow.ToUnixTimeMilliseconds();
            var hour = x.TryGetProperty("hour", out var hv) && hv.TryGetInt32(out var h) ? h : DateTime.Now.Hour;
            var minute = x.TryGetProperty("minute", out var mv) && mv.TryGetInt32(out var m) ? m : DateTime.Now.Minute;
            long at = 0;
            if (x.TryGetProperty("oneShotAt", out var ov) && ov.TryGetInt64(out var one) && one > 0) at = one;
            else if (x.TryGetProperty("nextAt", out var nv) && nv.TryGetInt64(out var next) && next > 0) at = next;
            var due = at > 0 ? DateTimeOffset.FromUnixTimeMilliseconds(at).LocalDateTime : DateTime.Today.AddHours(hour).AddMinutes(minute);
            if (at <= 0 && due < DateTime.Now) due = due.AddDays(1);
            var a = new AlarmItem
            {
                LocalId = id,
                Title = x.TryGetProperty("title", out var t) ? t.GetString() ?? "Reminder" : "Reminder",
                Details = x.TryGetProperty("details", out var d) ? d.GetString() ?? "" : "",
                Category = x.TryGetProperty("category", out var c) ? c.GetString() ?? "General" : "General",
                DueAt = due,
                BaseHour = hour,
                BaseMinute = minute,
                IsImportant = x.TryGetProperty("important", out var imp) && imp.ValueKind == JsonValueKind.True,
                IsEnabled = !x.TryGetProperty("enabled", out var en) || en.ValueKind == JsonValueKind.True,
                Vibrate = !x.TryGetProperty("strongVibration", out var vib) || vib.ValueKind == JsonValueKind.True,
                BypassSilent = !x.TryGetProperty("bypassSilent", out var bp) || bp.ValueKind == JsonValueKind.True,
                CloudClientId = x.TryGetProperty("cloudClientId", out var cc) ? cc.GetString() ?? "" : "",
                CloudVersion = x.TryGetProperty("cloudVersion", out var cv) && cv.TryGetInt64(out var ver) ? ver : 0,
                LastAction = x.TryGetProperty("lastAction", out var la) ? la.GetString() ?? "edit" : "edit",
                SnoozeMinutes = x.TryGetProperty("snoozeMinutes", out var sm) && sm.TryGetInt32(out var snooze) ? snooze : 5
            };
            if (x.TryGetProperty("days", out var days) && days.ValueKind == JsonValueKind.Array)
            {
                var list = days.EnumerateArray().Select(v => v.ValueKind == JsonValueKind.True).Take(7).ToList();
                while (list.Count < 7) list.Add(false);
                a.RepeatDays = list.ToArray();
            }
            a.IsDone = !a.IsEnabled;
            a.EnsureClientId();
            result.Add(a);
        }
        return result;
    }

    private void SaveAndRefresh()
    {
        _store.Save(_alarms);
        AlarmList.Items.Refresh();
    }

    protected override void OnClosing(System.ComponentModel.CancelEventArgs e)
    {
        if (!_allowClose)
        {
            e.Cancel = true;
            Hide();
            _tray.ShowBalloonTip(1500, "PULSE is still running", "Alarms and cloud sync remain active in the system tray.", Forms.ToolTipIcon.Info);
        }
        base.OnClosing(e);
    }

    private void RestoreWindow() { Show(); WindowState = WindowState.Normal; Activate(); }
    private void ExitApp() { _allowClose = true; _tray.Dispose(); System.Windows.Application.Current.Shutdown(); }
}
