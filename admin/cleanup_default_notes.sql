-- Clean up default notes for mayor's appointments
-- This script removes the default concatenated notes that were automatically generated

UPDATE schedule 
SET note = NULL 
WHERE note LIKE "Mayor's appointment: %" 
AND mayor_id IS NOT NULL;

-- Verify the cleanup
SELECT s.sched_id, s.note, m.appointment_title 
FROM schedule s 
JOIN mayors_appointment m ON s.mayor_id = m.id 
WHERE s.note IS NOT NULL;
