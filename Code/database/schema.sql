CREATE TABLE IF NOT EXISTS applications (
    id INTEGER PROMARY KEY AUTOINCREMENT,
    company TEXT NOT NULL,
    position TEXT NOT NULL,
    location TEXT,
    status TEXT DEFAULT 'Pending',
    applied_date TEXT,
    notes TEXT
);