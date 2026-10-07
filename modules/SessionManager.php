<?php
require_once __DIR__ . '/../core/bootstrap.php';

class SessionManager {
    private $conn;
    public function __construct($conn) { $this->conn = $conn; }

    public function forEvent($event_id) {
        $stmt = $this->conn->prepare(
            "SELECT s.*,
                (SELECT COUNT(*) FROM session_attendance sa WHERE sa.session_id = s.id) AS attended,
                (SELECT COUNT(*) FROM feedback f WHERE f.session_id = s.id) AS feedback_count,
                (SELECT ROUND(AVG(f.rating),1) FROM feedback f WHERE f.session_id = s.id) AS avg_rating
             FROM sessions s WHERE s.event_id = ?
             ORDER BY s.session_date, s.start_time, s.id");
        $stmt->bind_param("i", $event_id);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function find($id) {
        $stmt = $this->conn->prepare(
            "SELECT s.*, e.event_name, e.status AS event_status
             FROM sessions s JOIN events e ON e.id = s.event_id WHERE s.id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    public function save($data) {
        $id       = (int)($data['id'] ?? 0);
        $event_id = (int)$data['event_id'];
        $name     = capitalizeWords($data['session_name']);
        $speaker  = capitalizeWords($data['speaker_name'] ?? '');
        $role     = capitalizeWords($data['speaker_role'] ?? '');
        $date     = $data['session_date'] ?: null;
        $start    = $data['start_time'] ?: null;
        $end      = $data['end_time'] ?: null;
        $location = capitalizeWords($data['location'] ?? '');
        $desc     = trim($data['description'] ?? '');

        if ($name === '') return ['ok' => false, 'msg' => 'Session name is required.'];
        if ($date && !isValidEventDate($date)) return ['ok' => false, 'msg' => 'Invalid session date. Year must be between 2000 and 2100.'];
        if ($start && $end && $end < $start) return ['ok' => false, 'msg' => 'End time must be after the start time.'];

        if ($id > 0) {
            $stmt = $this->conn->prepare(
                "UPDATE sessions SET session_name=?, speaker_name=?, speaker_role=?,
                 session_date=?, start_time=?, end_time=?, location=?, description=? WHERE id=?");
            $stmt->bind_param("ssssssssi", $name, $speaker, $role, $date, $start, $end, $location, $desc, $id);
            $ok = $stmt->execute();
            return ['ok' => $ok, 'msg' => $ok ? 'Session updated.' : $this->conn->error, 'id' => $id];
        }

        $stmt = $this->conn->prepare(
            "INSERT INTO sessions (event_id, session_name, speaker_name, speaker_role,
             session_date, start_time, end_time, location, description) VALUES (?,?,?,?,?,?,?,?,?)");
        $stmt->bind_param("issssssss", $event_id, $name, $speaker, $role, $date, $start, $end, $location, $desc);
        $ok = $stmt->execute();
        return ['ok' => $ok, 'msg' => $ok ? 'Session added.' : $this->conn->error, 'id' => $this->conn->insert_id];
    }

    public function delete($id) {
        $stmt = $this->conn->prepare("DELETE FROM sessions WHERE id = ?");
        $stmt->bind_param("i", $id);
        return $stmt->execute();
    }

    public function attendees($session_id) {
        $stmt = $this->conn->prepare(
            "SELECT a.full_name, a.company, a.attendee_code, sa.scanned_at
             FROM session_attendance sa JOIN attendees a ON a.id = sa.attendee_id
             WHERE sa.session_id = ? ORDER BY sa.scanned_at DESC");
        $stmt->bind_param("i", $session_id);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function recordScan($session_id, $attendee_id, $staff_id) {
        $stmt = $this->conn->prepare(
            "INSERT IGNORE INTO session_attendance (session_id, attendee_id, scanned_by) VALUES (?,?,?)");
        $stmt->bind_param("iii", $session_id, $attendee_id, $staff_id);
        $stmt->execute();
        return $stmt->affected_rows > 0;
    }
}
