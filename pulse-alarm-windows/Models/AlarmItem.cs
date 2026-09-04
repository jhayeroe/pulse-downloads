namespace PulseAlarm.Windows.Models;

public sealed class AlarmItem
{
    public Guid Id { get; set; } = Guid.NewGuid();
    public string Title { get; set; } = "Reminder";
    public string Details { get; set; } = "";
    public DateTime DueAt { get; set; } = DateTime.Now.AddMinutes(5);
    public bool IsImportant { get; set; }
    public bool IsEnabled { get; set; } = true;
    public bool IsDone { get; set; }
    public bool HasFired { get; set; }
    public int SnoozeMinutes { get; set; } = 5;
}
