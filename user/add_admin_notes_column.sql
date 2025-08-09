-- Add admin_notes column to appointments table for mayor-only notes
ALTER TABLE appointments ADD COLUMN admin_notes TEXT NULL COMMENT 'Admin notes visible only to mayor';

-- Add index for better performance when querying by admin_notes
CREATE INDEX idx_admin_notes ON appointments(admin_notes(255)); 