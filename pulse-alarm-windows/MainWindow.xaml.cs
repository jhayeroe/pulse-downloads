using System.Collections.ObjectModel;
using System.Windows;
using System.Windows.Threading;
using PulseAlarm.Windows.Models;
using PulseAlarm.Windows.Services;
using Forms = System.Windows.Forms;

namespace PulseAlarm.Windows;

public partial class MainWindow : Window
{
    private readonly ObservableCollection<AlarmItem> _alarms;
    private readonly AlarmStore _store = new();
    private readonly DispatcherTimer _timer = new() { Interval = TimeSpan.FromSeconds(1) };
    private readonly Forms.NotifyIcon _tray;
    private bool _allowClose;

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
        menu.Items.Add("Exit", null, (_, _) => ExitApp());
        _tray.ContextMenuStrip = menu;
    }

    private void Timer_Tick(object? sender, EventArgs e)
    {
        ClockText.Text = DateTime.Now.ToString("h:mm:ss tt");
        DateText.Text = DateTime.Now.ToString("dddd, MMMM d");
        foreach (var alarm in _alarms.Where(a => a.IsEnabled && !a.IsDone && !a.HasFired && a.DueAt <= DateTime.Now).ToList())
        {
            alarm.HasFired = true;
            _store.Save(_alarms);
            new AlarmWindow(alarm, MeetingToggle.IsChecked == true, SaveAndRefresh).Show();
        }
    }

    private void AddAlarm_Click(object sender, RoutedEventArgs e)
    {
        var dialog = new AddAlarmWindow { Owner = this };
        if (dialog.ShowDialog() == true && dialog.Alarm is not null)
        {
            _alarms.Add(dialog.Alarm);
            SaveAndRefresh();
        }
    }

    private void Done_Click(object sender, RoutedEventArgs e)
    {
        if ((sender as FrameworkElement)?.Tag is AlarmItem alarm)
        {
            alarm.IsDone = true;
            SaveAndRefresh();
        }
    }

    private void MeetingToggle_OnChanged(object sender, RoutedEventArgs e)
    {
        if (!IsLoaded) return;
        MeetingToggle.Content = MeetingToggle.IsChecked == true ? "ON" : "OFF";
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
            _tray.ShowBalloonTip(1500, "PULSE is still running", "Alarms remain active in the system tray.", Forms.ToolTipIcon.Info);
        }
        base.OnClosing(e);
    }

    private void RestoreWindow() { Show(); WindowState = WindowState.Normal; Activate(); }
    private void ExitApp() { _allowClose = true; _tray.Dispose(); System.Windows.Application.Current.Shutdown(); }
}
