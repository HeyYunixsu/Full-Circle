<?php

function getActiveEvent() {
    global $conn;
    // A live (ongoing) event always wins; otherwise the next upcoming one
    $sql = "SELECT * FROM events WHERE status IN ('upcoming', 'ongoing') ORDER BY status = 'ongoing' DESC, event_date ASC LIMIT 1";
    $result = $conn->query($sql);
    return $result->num_rows > 0 ? $result->fetch_assoc() : null;
}

// One event id, or a list of ids for combined totals (dashboard overview across live events).
function getEventStats($event_id) {
    global $conn;

    $stats = [
        'total' => 0,
        'checked_in' => 0,
        'not_yet' => 0,
        'walk_ins' => 0,
        'percentage' => 0
    ];

    $sql = "SELECT
                COUNT(*) as total,
                SUM(CASE WHEN status = 'checked_in' THEN 1 ELSE 0 END) as checked_in,
                SUM(CASE WHEN status = 'not_yet' THEN 1 ELSE 0 END) as not_yet,
                SUM(CASE WHEN registration_type = 'walk-in' THEN 1 ELSE 0 END) as walk_ins
            FROM attendees WHERE event_id IN (" . (implode(',', array_map('intval', (array)$event_id)) ?: '0') . ")";

    $result = $conn->query($sql)->fetch_assoc();

    if ($result) {
        $stats['total'] = (int)$result['total'];
        $stats['checked_in'] = (int)$result['checked_in'];
        $stats['not_yet'] = (int)$result['not_yet'];
        $stats['walk_ins'] = (int)$result['walk_ins'];
        $stats['percentage'] = $stats['total'] > 0 ? round(($stats['checked_in'] / $stats['total']) * 100, 1) : 0;
    }

    return $stats;
}

// Event picker shared by Reports, Feedback and Companies: ?event_id= or the latest event.
// Returns [all events for the dropdown, selected event row or null].
function selectedEvent() {
    global $conn;
    $events = $conn->query("SELECT id, event_name, event_date, status FROM events ORDER BY event_date DESC, id DESC")->fetch_all(MYSQLI_ASSOC);
    $id = (int)($_GET['event_id'] ?? $_POST['event_id'] ?? ($events[0]['id'] ?? 0));
    $stmt = $conn->prepare("SELECT * FROM events WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    return [$events, $stmt->get_result()->fetch_assoc()];
}

// Horizontal bar with a value label, for tables.
function meter($value, $max, $label = null) {
    $pct = $max > 0 ? min(100, round($value / $max * 100)) : 0;
    return '<div class="meter"><span><i style="width:' . $pct . '%"></i></span><em>' . ($label ?? $value) . '</em></div>';
}

// Companies of an event with registered / checked-in counts and their limit (cap NULL = no limit).
// Names come from the companies table and from attendees, so typed-in companies are counted too.
function companyCapacity($event_id) {
    global $conn;
    $s = $conn->prepare("SELECT name, SUM(total) total, SUM(checked_in) checked_in, MAX(cap) cap FROM (
                            SELECT company_name name, 0 total, 0 checked_in, max_attendees cap FROM companies WHERE event_id = ?
                            UNION ALL
                            SELECT company, 1, status = 'checked_in', NULL FROM attendees WHERE event_id = ? AND company IS NOT NULL AND company <> ''
                         ) t GROUP BY name ORDER BY total DESC, name");
    $s->bind_param("ii", $event_id, $event_id);
    $s->execute();
    return $s->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Status pill for a count against a limit: Full / Over by N / Almost full (90%+). Empty when there is room or no limit.
function capacityBadge($count, $cap) {
    if (!$cap) return '';
    if ($count > $cap)   return '<span class="pill-bad">Over by ' . ($count - $cap) . '</span>';
    if ($count >= $cap)  return '<span class="pill-bad">Full</span>';
    if ($count >= $cap * 0.9) return '<span class="pill-warn">Almost full</span>';
    return '';
}

function findEvent($event_id) {
    global $conn;
    $s = $conn->prepare("SELECT * FROM events WHERE id = ?");
    $s->bind_param("i", $event_id);
    $s->execute();
    return $s->get_result()->fetch_assoc();
}

// Check-in rule: open while the event is Ongoing (any date, for multi-day events or a dry run) or on the
// event day itself; closed before that day and once the event is completed or archived.
// Returns null when check-in is open, otherwise the reason to show staff.
function checkinClosedReason($event) {
    if ($event['status'] === 'ongoing') return null;
    if (in_array($event['status'], ['completed', 'archived'], true)) return 'This event has ended, so check-in is closed.';
    $today = date('Y-m-d');
    if ($event['event_date'] === $today) return null;
    if ($event['event_date'] > $today) {
        return 'Check-in opens on ' . date('F j, Y', strtotime($event['event_date'])) . '. To open it early, an admin can click "Mark as Ongoing" on the event page.';
    }
    return 'The event date has passed. To reopen check-in, an admin can click "Mark as Ongoing" on the event page.';
}

// On the event day the first check-in marks an Upcoming event Ongoing, so dashboards and staff permissions follow.
function markOngoingIfEventDay($event) {
    global $conn;
    if ($event['status'] !== 'upcoming' || $event['event_date'] !== date('Y-m-d')) return;
    $s = $conn->prepare("UPDATE events SET status = 'ongoing' WHERE id = ? AND status = 'upcoming'");
    $s->bind_param("i", $event['id']);
    $s->execute();
    if ($s->affected_rows) logActivity('Event Status Updated', "Event '{$event['event_name']}' marked Ongoing by its first check-in");
}

// What the dashboard overview counts: every Ongoing event, or the next Upcoming one when nothing is live.
// Returns [events, kind] with kind 'live', 'next' or '' (no events).
function overviewEvents() {
    global $conn;
    $live = $conn->query("SELECT * FROM events WHERE status = 'ongoing' ORDER BY event_date, id")->fetch_all(MYSQLI_ASSOC);
    if ($live) return [$live, 'live'];
    $next = getActiveEvent();
    return $next ? [[$next], 'next'] : [[], ''];
}
