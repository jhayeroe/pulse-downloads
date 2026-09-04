namespace PulseAlarm.Windows.Models;

public sealed class AlarmItem
{
    public Guid Id { get; set; } = Guid.NewGuid();
    public long LocalId { get; set; } = DateTimeOffset.UtcNow.ToUnixTimeMilliseconds();
    public string CloudClientId { get; set; } = "";
    public long CloudVersion { get; set; }

    public string Title { get; set; } = "Reminder";
    public string Details { get; set; } = "";
    public string Category { get; set; } = "General";
    public DateTime DueAt { get; set; } = DateTime.Now.AddMinutes(5);
    public bool IsImportant { get; set; }
    public bool IsEnabled { get; set; } = true;
    public bool IsDone { get; set; }
    public bool HasFired { get; set; }
    public int SnoozeMinutes { get; set; } = 5;

    public bool Vibrate { get; set; } = true;
    public bool BypassSilent { get; set; } = true;
    public bool[] RepeatDays { get; set; } = new bool[7];
    public int BaseHour { get; set; }
    public int BaseMinute { get; set; }
    public string Timezone { get; set; } = TimeZoneInfo.Local.Id;
    public string LastAction { get; set; } = "edit";

    public string EnsureClientId()
    {
        if (string.IsNullOrWhiteSpace(CloudClientId)) CloudClientId = $"windows:{Id:N}";
        return CloudClientId;
    }
}
