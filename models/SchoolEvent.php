<?php
require_once __DIR__ . '/../config/database.php';

class SchoolEvent {
    private $conn;
    
    public function __construct() {
        $this->conn = getConnection();
    }
    
    public function getUpcoming($limit = 5) {
        $stmt = $this->conn->prepare("
            SELECT * FROM school_events 
            WHERE event_date >= CURRENT_DATE 
            ORDER BY event_date ASC 
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    }
    
    public function getByMonth($year, $month) {
        $startDate = "$year-$month-01";
        $endDate = date('Y-m-t', strtotime($startDate));
        
        $stmt = $this->conn->prepare("
            SELECT * FROM school_events 
            WHERE event_date BETWEEN ? AND ?
            ORDER BY event_date
        ");
        $stmt->execute([$startDate, $endDate]);
        return $stmt->fetchAll();
    }
    
    public function getHolidays($year) {
        $stmt = $this->conn->prepare("
            SELECT * FROM school_events 
            WHERE is_holiday = true AND EXTRACT(YEAR FROM event_date) = ?
            ORDER BY event_date
        ");
        $stmt->execute([$year]);
        return $stmt->fetchAll();
    }
    
    public function create($data) {
        $stmt = $this->conn->prepare("
            INSERT INTO school_events (title, description, event_date, event_type, is_holiday, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        return $stmt->execute([
            $data['title'],
            $data['description'] ?? null,
            $data['event_date'],
            $data['event_type'] ?? 'event',
            $data['is_holiday'] ?? false
        ]);
    }
    
    public function update($id, $data) {
        $stmt = $this->conn->prepare("
            UPDATE school_events 
            SET title = ?, description = ?, event_date = ?, event_type = ?, is_holiday = ?
            WHERE id = ?
        ");
        return $stmt->execute([
            $data['title'],
            $data['description'] ?? null,
            $data['event_date'],
            $data['event_type'] ?? 'event',
            $data['is_holiday'] ?? false,
            $id
        ]);
    }
    
    public function delete($id) {
        $stmt = $this->conn->prepare("DELETE FROM school_events WHERE id = ?");
        return $stmt->execute([$id]);
    }
    
    public function isHoliday($date) {
        $stmt = $this->conn->prepare("
            SELECT COUNT(*) as count FROM school_events 
            WHERE event_date = ? AND is_holiday = true
        ");
        $stmt->execute([$date]);
        return $stmt->fetch()['count'] > 0;
    }
}
