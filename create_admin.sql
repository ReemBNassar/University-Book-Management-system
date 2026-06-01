-- create_admin.sql
-- إنشاء حساب مشرف (Admin)
-- ------------------------------------------------------------
-- مهم: عمود password_hash لازم يكون هاش (مش كلمة سر صريحة).
-- الطريقة الأسهل: استخدمي صفحة make_admin.php المرفقة (تفتحيها في المتصفح مرة واحدة)
-- أو ولّدي الهاش يدويًا ثم ضعيه مكان <HASH> تحت.
--
-- لتوليد هاش يدويًا من سطر الأوامر:
--   php -r "echo password_hash('ضعي_كلمة_السر_هنا', PASSWORD_DEFAULT);"
-- ------------------------------------------------------------

INSERT INTO user_account (full_name, email, password_hash, role)
VALUES ('Admin', 'admin@unibook.com', '<HASH>', 'Admin');

INSERT INTO admin_user (user_id)
SELECT user_id FROM user_account WHERE email = 'admin@unibook.com';
