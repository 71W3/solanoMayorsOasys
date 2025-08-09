-- Update users table to support OAuth (Google and GitHub)
ALTER TABLE users 
ADD COLUMN google_id VARCHAR(255) NULL AFTER email,
ADD COLUMN github_id VARCHAR(255) NULL AFTER google_id,
ADD COLUMN profile_picture VARCHAR(500) NULL AFTER github_id,
ADD COLUMN role ENUM('user', 'mayor', 'admin') DEFAULT 'user' AFTER address;

-- Add index for OAuth ID lookups
CREATE INDEX idx_google_id ON users(google_id);
CREATE INDEX idx_github_id ON users(github_id);

-- Add index for role-based queries
CREATE INDEX idx_user_role ON users(role);

-- Update existing users to have 'user' role if not set
UPDATE users SET role = 'user' WHERE role IS NULL; 