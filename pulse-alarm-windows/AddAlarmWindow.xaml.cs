using System.Globalization;
using System.Windows;
using PulseAlarm.Windows.Models;

namespace PulseAlarm.Windows;

public partial class AddAlarmWindow : Window
{
    public AlarmItem? Alarm { get; private set; }

    public AddAlarmWindow()
    {
        InitializeComponent();
        DatePicker.SelectedDate = DateTime.Today;
        TimeBox.Text = DateTime.Now.AddMinutes(5).ToString("h:mm tt");
    }

    private void Save_Click(object sender, RoutedEventArgs e)
    {
        if (DatePicker.SelectedDate is null || !DateTime.TryParse(TimeBox.Text, CultureInfo.CurrentCulture, DateTimeStyles.None, out var parsed))
        {
            System.Windows.MessageBox.Show("Please enter a valid date and time, e.g. 3:30 PM.", "PULSE Alarm");
            return;
        }
        var due = DatePicker.SelectedDate.Value.Date.Add(parsed.TimeOfDay);
        Alarm = new AlarmItem
        {
            Title = string.IsNullOrWhiteSpace(TitleBox.Text) ? "Reminder" : TitleBox.Text.Trim(),
            Details = DetailsBox.Text.Trim(), DueAt = due, IsImportant = ImportantBox.IsChecked == true
        };
        DialogResult = true;
    }
}
