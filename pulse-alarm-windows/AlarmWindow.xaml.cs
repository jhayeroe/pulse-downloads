using System.Media;
using System.Windows;
using System.Windows.Input;
using System.Windows.Threading;
using PulseAlarm.Windows.Models;

namespace PulseAlarm.Windows;

public partial class AlarmWindow : Window
{
    private readonly AlarmItem _alarm;
    private readonly Action _save;
    private readonly DispatcherTimer _hold = new() { Interval = TimeSpan.FromSeconds(2) };

    public AlarmWindow(AlarmItem alarm, bool quiet, Action save)
    {
        InitializeComponent();
        _alarm = alarm; _save = save;
        TitleText.Text = alarm.Title;
        DetailsText.Text = alarm.Details;
        DueText.Text = alarm.DueAt.ToString("h:mm tt");
        _hold.Tick += (_, _) => { _hold.Stop(); Complete(); };
        if (!quiet) SystemSounds.Exclamation.Play();
    }

    private void Snooze_Click(object sender, RoutedEventArgs e)
    {
        _alarm.DueAt = DateTime.Now.AddMinutes(_alarm.SnoozeMinutes);
        _alarm.HasFired = false;
        _save(); Close();
    }
    private void Done_Down(object sender, MouseButtonEventArgs e) => _hold.Start();
    private void Done_Up(object sender, MouseButtonEventArgs e) => _hold.Stop();
    private void Complete() { _alarm.IsDone = true; _save(); Close(); }
}
