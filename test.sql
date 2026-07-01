-- 1. إنشاء الجداول (تم دمج الـ ENUMs مباشرة داخل الأعمدة)

CREATE TABLE user_account (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('Student', 'Admin') NOT NULL
);

CREATE TABLE student (
    user_id INT PRIMARY KEY,
    department VARCHAR(50) NOT NULL,
    current_borrow_count INT DEFAULT 0,
    CONSTRAINT fk_student_user FOREIGN KEY (user_id) REFERENCES user_account(user_id) ON DELETE CASCADE,
    CONSTRAINT chk_borrow_count CHECK (current_borrow_count BETWEEN 0 AND 3)
);

CREATE TABLE admin_user (
    user_id INT PRIMARY KEY,
    CONSTRAINT fk_admin_user FOREIGN KEY (user_id) REFERENCES user_account(user_id) ON DELETE CASCADE
);

CREATE TABLE book (
    book_id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL, 
    author VARCHAR(100) NOT NULL, 
    department VARCHAR(50), 
    status ENUM('Pending', 'Available', 'Borrowed', 'Damaged') DEFAULT 'Pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE borrowing_request (
    request_id INT AUTO_INCREMENT PRIMARY KEY,
    book_id INT,
    borrower_id INT,
    admin_id INT,
    request_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    return_deadline DATE,
    status ENUM('Pending', 'Approved', 'Rejected', 'Returned') DEFAULT 'Pending',
    rejection_reason VARCHAR(255) DEFAULT NULL,
    CONSTRAINT fk_req_book FOREIGN KEY (book_id) REFERENCES book(book_id) ON DELETE SET NULL,
    CONSTRAINT fk_req_borrower FOREIGN KEY (borrower_id) REFERENCES student(user_id) ON DELETE CASCADE,
    CONSTRAINT fk_req_admin FOREIGN KEY (admin_id) REFERENCES admin_user(user_id) ON DELETE SET NULL
);

CREATE TABLE borrowing_history (
    history_id INT AUTO_INCREMENT PRIMARY KEY,
    book_id INT,
    student_id INT,
    action_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    action_type VARCHAR(50), 
    note TEXT,
    CONSTRAINT fk_hist_book FOREIGN KEY (book_id) REFERENCES book(book_id) ON DELETE SET NULL,
    CONSTRAINT fk_hist_student FOREIGN KEY (student_id) REFERENCES student(user_id) ON DELETE CASCADE
);

CREATE TABLE waitlist (
    waitlist_id INT AUTO_INCREMENT PRIMARY KEY,
    book_id INT,
    user_id INT,
    UNIQUE (book_id, user_id),
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_wait_book FOREIGN KEY (book_id) REFERENCES book(book_id) ON DELETE CASCADE,
    CONSTRAINT fk_wait_user FOREIGN KEY (user_id) REFERENCES student(user_id) ON DELETE CASCADE
);

CREATE TABLE notification (
    notification_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    message TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_notif_user FOREIGN KEY (user_id) REFERENCES user_account(user_id) ON DELETE CASCADE
);

-- 2. إنشاء الفهارس (Indexes)
CREATE INDEX idx_book_search ON book (title);
CREATE INDEX idx_book_department ON book (department);

-- 3. إنشاء المشغل (Trigger) الخاص بحد الاستعارة
-- تم تغيير المحدِّد (DELIMITER) حتى لا يختلط السيمي-كولون الخاص بالمشغل مع كود الـ SQL الخارجي

DELIMITER $$

CREATE TRIGGER trg_check_borrow_limit
BEFORE UPDATE ON borrowing_request
FOR EACH ROW
BEGIN
    DECLARE current_count INT;

    -- حالة الموافقة على الطلب المعلق
    IF (NEW.status = 'Approved' AND OLD.status = 'Pending') THEN
        
        -- جلب عدد الكتب المستعارة حالياً للطالب
        SELECT current_borrow_count INTO current_count 
        FROM student 
        WHERE user_id = NEW.borrower_id;
        
        -- التحقق من الحد الأقصى (3 كتب) وإظهار خطأ مخصص في حال تجاوزه
        IF current_count >= 3 THEN
            SIGNAL SQLSTATE '45000' 
            SET MESSAGE_TEXT = 'عذراً: الطالب وصل للحد الأقصى (3 كتب)';
        END IF;

        -- تحديث البيانات
        UPDATE student SET current_borrow_count = current_borrow_count + 1 WHERE user_id = NEW.borrower_id;
        UPDATE book SET status = 'Borrowed' WHERE book_id = NEW.book_id;

    -- حالة إرجاع الكتاب المستعار
    ELSEIF (NEW.status = 'Returned' AND OLD.status = 'Approved') THEN
	
        UPDATE student SET current_borrow_count = current_borrow_count - 1 WHERE user_id = NEW.borrower_id;
        UPDATE book SET status = 'Available' WHERE book_id = NEW.book_id;
        
    END IF;
END$$

DELIMITER ;