CREATE TYPE user_role AS ENUM ('Student', 'Admin');
CREATE TYPE book_status AS ENUM ('Available', 'Borrowed', 'Damaged');
CREATE TYPE request_status AS ENUM ('Pending', 'Approved', 'Rejected', 'Returned');

CREATE TABLE user_account (
    user_id SERIAL PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role user_role NOT NULL
);

CREATE TABLE student (
    user_id INT PRIMARY KEY REFERENCES user_account(user_id) ON DELETE CASCADE,
    department VARCHAR(50) NOT NULL,
    current_borrow_count INT DEFAULT 0 CHECK (current_borrow_count BETWEEN 0 AND 3)
);

CREATE TABLE admin_user (
    user_id INT PRIMARY KEY REFERENCES user_account(user_id) ON DELETE CASCADE
);

CREATE TABLE book (
    book_id SERIAL PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    author VARCHAR(100) NOT NULL,
    department VARCHAR(50),
    status book_status DEFAULT 'Available',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE borrowing_request (
    request_id SERIAL PRIMARY KEY,
    book_id INT REFERENCES book(book_id) ON DELETE SET NULL,
    borrower_id INT REFERENCES student(user_id) ON DELETE CASCADE,
    admin_id INT REFERENCES admin_user(user_id) ON DELETE SET NULL,
    request_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    return_deadline DATE,
    status request_status DEFAULT 'Pending'
);

CREATE TABLE borrowing_history (
    history_id SERIAL PRIMARY KEY,
    book_id INT REFERENCES book(book_id) ON DELETE SET NULL,
    student_id INT REFERENCES student(user_id) ON DELETE CASCADE,
    action_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    action_type VARCHAR(50),
    note TEXT
);

CREATE TABLE waitlist (
    waitlist_id SERIAL PRIMARY KEY,
    book_id INT REFERENCES book(book_id) ON DELETE CASCADE,
    user_id INT REFERENCES student(user_id) ON DELETE CASCADE,
    UNIQUE (book_id, user_id),
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE notification (
    notification_id SERIAL PRIMARY KEY,
    user_id INT REFERENCES user_account(user_id) ON DELETE CASCADE,
    message TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE OR REPLACE FUNCTION handle_borrowing_limit()
RETURNS TRIGGER AS $$
BEGIN
    IF (NEW.status = 'Approved' AND OLD.status = 'Pending') THEN
        IF (SELECT current_borrow_count FROM student WHERE user_id = NEW.borrower_id) >= 3 THEN
            RAISE EXCEPTION 'عذراً: الطالب وصل للحد الأقصى (3 كتب)';
        END IF;
        UPDATE student SET current_borrow_count = current_borrow_count + 1 WHERE user_id = NEW.borrower_id;
        UPDATE book SET status = 'Borrowed' WHERE book_id = NEW.book_id;
    ELSIF (NEW.status = 'Returned' AND OLD.status = 'Approved') THEN
        UPDATE student SET current_borrow_count = current_borrow_count - 1 WHERE user_id = NEW.borrower_id;
        UPDATE book SET status = 'Available' WHERE book_id = NEW.book_id;
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_check_borrow_limit
BEFORE UPDATE ON borrowing_request
FOR EACH ROW EXECUTE FUNCTION handle_borrowing_limit();

CREATE INDEX idx_book_search ON book (title);
CREATE INDEX idx_book_department ON book (department);
