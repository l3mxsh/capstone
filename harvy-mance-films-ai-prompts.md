# AI Prompts: Harvy Mance Films Online Management System
### Built with Native PHP, MySQL, HTML, CSS, JavaScript, Bootstrap

---

> **Tech Stack for all prompts:**
> - **Backend:** Native PHP (no frameworks)
> - **Database:** MySQL (PDO)
> - **Frontend:** HTML5, CSS3, Bootstrap 5, Vanilla JavaScript
> - **Auth:** PHP Sessions + Role-Based Access Control (Admin / Client)
> - **IDE:** Visual Studio Code

---

## PAGE 1 — LANDING PAGE (`index.php`)

```
Build a Landing Page in native PHP called index.php for a photography and videography studio called "Harvy Mance Films."

LAYOUT SECTIONS:
1. Navigation Bar — logo on the left, links: Home, Packages, Book Now, Login. Use Bootstrap 5 navbar with sticky-top class.
2. Hero Section — full-width banner with tagline "Capture Your Moment Through Our Scope", a subtitle description of the studio, and a "Book Now" CTA button that links to the booking page (requires login).
3. Packages Preview Section — fetch and display all active photography packages from the MySQL `packages` table (columns: id, name, description, price, inclusions, status). Show each as a Bootstrap card with name, price, and a "View Details" button.
4. About Section — short paragraph about Harvy Mance Films, established 2019, located in Brgy. San Antonio, Biñan, Laguna.
5. Footer — contact info and copyright.

PHP LOGIC:
- Connect to MySQL using PDO in a separate file: config/db.php.
- On this page, run a SELECT query: SELECT * FROM packages WHERE status = 'active'.
- Loop through results and echo Bootstrap cards dynamically.
- If not logged in and user clicks "Book Now", redirect to login.php.

DATABASE TABLE NEEDED:
CREATE TABLE packages (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100),
  description TEXT,
  price DECIMAL(10,2),
  inclusions TEXT,
  status ENUM('active','archived') DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

STYLE:
- Dark cinematic theme (dark navy background, gold accents).
- Bootstrap 5 CDN.
- Custom CSS in assets/css/style.css.
- Fully responsive.
```

---

## PAGE 2 — LOGIN PAGE (`login.php`)

```
Build a Login Page in native PHP called login.php for a two-role system: Admin and Client.

LAYOUT:
- Centered login card using Bootstrap 5.
- Fields: Email, Password.
- "Login" submit button.
- No registration link (Admin creates client accounts).

PHP LOGIC:
- On POST, connect to MySQL via PDO (config/db.php).
- Query: SELECT * FROM users WHERE email = ? AND status = 'active'.
- Use password_verify() to check hashed password.
- On success, start session:
  - $_SESSION['user_id'], $_SESSION['role'] (either 'admin' or 'client'), $_SESSION['name'].
- Redirect admin to admin/dashboard.php.
- Redirect client to client/dashboard.php.
- On failure, show error: "Invalid credentials. Please try again."

DATABASE TABLE NEEDED:
CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100),
  email VARCHAR(100) UNIQUE,
  password VARCHAR(255),
  role ENUM('admin','client') DEFAULT 'client',
  status ENUM('active','inactive') DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

SECURITY:
- Use prepared statements (PDO) for all queries.
- Session regeneration after login: session_regenerate_id(true).
- Redirect already-logged-in users away from login page.
```

---

## PAGE 3 — ADMIN DASHBOARD (`admin/dashboard.php`)

```
Build an Admin Dashboard in native PHP called admin/dashboard.php for Harvy Mance Films.

ACCESS CONTROL:
- At the top of every admin page, include auth/admin_guard.php which checks:
  if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') { header('Location: ../login.php'); exit; }

LAYOUT — use a sidebar layout with Bootstrap 5:
- Sidebar: logo, nav links (Dashboard, Packages, Bookings, Staff, Cancellations, Post-Production, Clients, Reports, Logout).
- Top bar: welcome message showing $_SESSION['name'], global search bar.
- Main content area with these widgets:

WIDGETS (all data pulled from MySQL via PDO):
1. Summary Cards (4 cards in a row):
   - Total Bookings Today: SELECT COUNT(*) FROM bookings WHERE DATE(created_at) = CURDATE()
   - Pending Approvals: SELECT COUNT(*) FROM bookings WHERE status = 'pending'
   - Active Post-Production: SELECT COUNT(*) FROM post_production WHERE status != 'completed'
   - Total Clients: SELECT COUNT(*) FROM users WHERE role = 'client'

2. Recent Bookings Table (last 5):
   SELECT b.id, u.name, p.name AS package, b.booking_date, b.status
   FROM bookings b
   JOIN users u ON b.client_id = u.id
   JOIN packages p ON b.package_id = p.id
   ORDER BY b.created_at DESC LIMIT 5

3. Schedule Calendar — render a simple HTML/CSS monthly calendar. Highlight dates that have bookings (fetch all booking_date values for the current month from the bookings table).

4. Post-Production Progress List — show top 5 ongoing projects with a Bootstrap progress bar based on the `progress_percent` column in the post_production table.

STYLE:
- Dark sidebar (#1a1a2e), white main content.
- Bootstrap 5. Responsive.
```

---

## PAGE 4 — PHOTOGRAPHY PACKAGES MANAGEMENT (`admin/packages.php`)

```
Build a Packages Management Page in native PHP called admin/packages.php.

ACCESS CONTROL: Include auth/admin_guard.php at the top.

FEATURES:

1. LIST ALL PACKAGES — Bootstrap table showing: ID, Name, Price, Status, Actions (Edit, Archive).
   SELECT * FROM packages ORDER BY created_at DESC

2. ADD NEW PACKAGE — Bootstrap modal form with fields:
   - Package Name (text)
   - Description (textarea)
   - Price (number)
   - Inclusions (textarea — list what is included)
   - Status (dropdown: active / archived)
   On POST, validate inputs, then:
   INSERT INTO packages (name, description, price, inclusions, status) VALUES (?, ?, ?, ?, ?)

3. EDIT PACKAGE — pre-fill modal with existing data. On POST:
   UPDATE packages SET name=?, description=?, price=?, inclusions=?, status=? WHERE id=?

4. ARCHIVE PACKAGE — button that runs:
   UPDATE packages SET status='archived' WHERE id=?
   Archived packages no longer show on the landing page or booking form.

PHP LOGIC:
- All form submissions use POST method.
- Use PDO prepared statements.
- Show success/error messages using Bootstrap alerts (stored in $_SESSION['flash']).
- Redirect after POST to prevent form resubmission (PRG pattern).

DATABASE TABLE: packages (already defined in Page 1 prompt).
```

---

## PAGE 5 — BOOKING MANAGEMENT (`admin/bookings.php`)

```
Build a Booking Management Page in native PHP called admin/bookings.php.

ACCESS CONTROL: Include auth/admin_guard.php.

DATABASE TABLE NEEDED:
CREATE TABLE bookings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  client_id INT,
  package_id INT,
  booking_date DATE,
  event_type VARCHAR(100),
  venue VARCHAR(255),
  notes TEXT,
  status ENUM('pending','approved','rescheduled','cancelled') DEFAULT 'pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (client_id) REFERENCES users(id),
  FOREIGN KEY (package_id) REFERENCES packages(id)
);

FEATURES:

1. LIST ALL BOOKINGS — Bootstrap table with columns:
   ID, Client Name, Package, Booking Date, Event Type, Status (colored badge), Actions.
   Use JOIN to get client name and package name.
   Add filter dropdown to filter by status (pending / approved / cancelled).

2. VIEW BOOKING DETAILS — clicking a booking opens a modal or detail page showing all fields.

3. APPROVE BOOKING:
   - Button that sets: UPDATE bookings SET status='approved' WHERE id=?
   - After approving, auto-assign staff using this logic:
     SELECT id FROM staff WHERE id NOT IN (
       SELECT staff_id FROM staff_schedules WHERE booking_date = ?
     ) LIMIT 1
     Then: INSERT INTO staff_schedules (staff_id, booking_id, booking_date) VALUES (?, ?, ?)
   - Show error if no staff is available.

4. RESCHEDULE BOOKING — admin can change booking_date. Check for conflicts first:
   SELECT COUNT(*) FROM bookings WHERE booking_date = ? AND status = 'approved' AND id != ?

5. REJECT BOOKING:
   UPDATE bookings SET status='cancelled' WHERE id=?
   Then trigger cancellation logic (update slot availability).

PHP LOGIC:
- Use PDO prepared statements.
- Use PRG pattern (Post-Redirect-Get).
- Flash messages for all actions.
```

---

## PAGE 6 — STAFF SCHEDULING (`admin/staff.php`)

```
Build a Staff Scheduling Page in native PHP called admin/staff.php.

ACCESS CONTROL: Include auth/admin_guard.php.

DATABASE TABLES NEEDED:
CREATE TABLE staff (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100),
  role VARCHAR(100),
  email VARCHAR(100),
  phone VARCHAR(20),
  status ENUM('active','inactive') DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE staff_schedules (
  id INT AUTO_INCREMENT PRIMARY KEY,
  staff_id INT,
  booking_id INT,
  booking_date DATE,
  FOREIGN KEY (staff_id) REFERENCES staff(id),
  FOREIGN KEY (booking_id) REFERENCES bookings(id)
);

FEATURES:

1. STAFF LIST — Bootstrap table: Name, Role, Email, Phone, Status, Actions (Edit, Deactivate, View Schedule).

2. ADD STAFF — modal form: Name, Role, Email, Phone.
   INSERT INTO staff (name, role, email, phone) VALUES (?, ?, ?, ?)

3. EDIT STAFF — pre-filled modal. UPDATE staff SET ... WHERE id=?

4. VIEW STAFF SCHEDULE — for a selected staff, show all their assigned bookings:
   SELECT b.booking_date, b.event_type, b.venue, u.name AS client
   FROM staff_schedules ss
   JOIN bookings b ON ss.booking_id = b.id
   JOIN users u ON b.client_id = u.id
   WHERE ss.staff_id = ?
   ORDER BY b.booking_date ASC

5. MANUAL STAFF ASSIGNMENT — dropdown to change staff assignment for a booking:
   UPDATE staff_schedules SET staff_id = ? WHERE booking_id = ?
   Before updating, check for conflicts:
   SELECT COUNT(*) FROM staff_schedules WHERE staff_id = ? AND booking_date = ? AND booking_id != ?

6. CONFLICT DETECTION ALERT — if a staff member is already assigned on a date, show a red Bootstrap alert: "This staff is already assigned on [date]."
```

---

## PAGE 7 — CANCELLATION MANAGEMENT (`admin/cancellations.php`)

```
Build a Cancellation Management Page in native PHP called admin/cancellations.php.

ACCESS CONTROL: Include auth/admin_guard.php.

DATABASE TABLE NEEDED:
CREATE TABLE cancellations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  booking_id INT,
  client_id INT,
  reason TEXT,
  deposit_amount DECIMAL(10,2),
  deposit_retained DECIMAL(10,2),
  cancelled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (booking_id) REFERENCES bookings(id),
  FOREIGN KEY (client_id) REFERENCES users(id)
);

FEATURES:

1. CANCELLATION LOG TABLE — Bootstrap table showing:
   ID, Client Name, Package, Original Booking Date, Reason, Deposit Retained, Date Cancelled.
   SELECT c.*, u.name, p.name AS package, b.booking_date
   FROM cancellations c
   JOIN bookings b ON c.booking_id = b.id
   JOIN users u ON c.client_id = u.id
   JOIN packages p ON b.package_id = p.id
   ORDER BY c.cancelled_at DESC

2. PROCESS CANCELLATION (triggered from bookings.php):
   Step 1: UPDATE bookings SET status='cancelled' WHERE id=?
   Step 2: DELETE FROM staff_schedules WHERE booking_id=?
   Step 3: INSERT INTO cancellations (booking_id, client_id, reason, deposit_amount, deposit_retained) VALUES (?, ?, ?, ?, ?)
   Step 4: Send email notification using PHP mail() to the client.

3. DEPOSIT TRACKING FORM — when processing a cancellation, admin inputs:
   - Reason for cancellation (textarea)
   - Deposit Amount Paid (number)
   - Amount to Retain (number)

4. SUMMARY CARD at top of page:
   - Total Cancellations This Month
   - Total Deposits Retained This Month
   SELECT COUNT(*), SUM(deposit_retained) FROM cancellations WHERE MONTH(cancelled_at) = MONTH(CURDATE())

PHP LOGIC: PDO prepared statements. PRG pattern. Flash messages.
```

---

## PAGE 8 — POST-PRODUCTION TRACKER (`admin/post_production.php`)

```
Build a Post-Production Tracker Page in native PHP called admin/post_production.php.

ACCESS CONTROL: Include auth/admin_guard.php.

DATABASE TABLE NEEDED:
CREATE TABLE post_production (
  id INT AUTO_INCREMENT PRIMARY KEY,
  booking_id INT,
  photo_status ENUM('not_started','in_progress','completed') DEFAULT 'not_started',
  video_status ENUM('not_started','in_progress','completed') DEFAULT 'not_started',
  other_status ENUM('not_started','in_progress','completed') DEFAULT 'not_started',
  progress_percent INT DEFAULT 0,
  deadline DATE,
  deadline_status ENUM('early','near','late') DEFAULT 'early',
  notes TEXT,
  drive_link VARCHAR(500),
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (booking_id) REFERENCES bookings(id)
);

FEATURES:

1. PROJECT LIST TABLE — Bootstrap table showing:
   Client Name, Package, Event Date, Photo Status, Video Status, Progress %, Deadline, Deadline Status (badge), Actions.
   SELECT pp.*, u.name, p.name AS package, b.booking_date
   FROM post_production pp
   JOIN bookings b ON pp.booking_id = b.id
   JOIN users u ON b.client_id = u.id
   JOIN packages p ON b.package_id = p.id

2. PROGRESS UPDATE FORM (modal per project):
   - Photo Status (dropdown: not_started / in_progress / completed)
   - Video Status (dropdown)
   - Other Deliverables Status (dropdown)
   - Progress % (number 0–100, or auto-calculate from the 3 statuses)
   - Deadline Date (date picker)
   - Notes (textarea)
   - Google Drive Link (text input — shareable link for client access)
   On POST: UPDATE post_production SET ... WHERE id=?

3. DEADLINE STATUS AUTO-CALCULATION in PHP:
   $today = new DateTime();
   $deadline = new DateTime($row['deadline']);
   $diff = $today->diff($deadline)->days;
   if ($deadline < $today) $status = 'late';
   elseif ($diff <= 3) $status = 'near';
   else $status = 'early';
   Show as Bootstrap badge: red=late, yellow=near, green=early.

4. BOOTSTRAP PROGRESS BAR — display progress_percent visually per project row.

5. MARK AS COMPLETED — button: UPDATE post_production SET photo_status='completed', video_status='completed', other_status='completed', progress_percent=100 WHERE id=?
   Then send email notification to client using PHP mail().
```

---

## PAGE 9 — CLIENT PORTAL DASHBOARD (`client/dashboard.php`)

```
Build a Client Portal Dashboard in native PHP called client/dashboard.php.

ACCESS CONTROL:
- Include auth/client_guard.php:
  if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'client') { header('Location: ../login.php'); exit; }

LAYOUT:
- Top navbar: Studio logo, "My Portal", Logout.
- Left sidebar: Dashboard, My Bookings, Post-Production Status, My Invoices, Download Files.
- Main content area.

WIDGETS:

1. WELCOME BANNER — "Welcome back, [client name]!"

2. BOOKING SUMMARY CARD — show the client's latest booking:
   SELECT b.*, p.name AS package FROM bookings b
   JOIN packages p ON b.package_id = p.id
   WHERE b.client_id = ? ORDER BY b.created_at DESC LIMIT 1

3. POST-PRODUCTION STATUS — if an approved booking exists, show the progress:
   SELECT pp.photo_status, pp.video_status, pp.progress_percent, pp.deadline_status
   FROM post_production pp
   JOIN bookings b ON pp.booking_id = b.id
   WHERE b.client_id = ? AND b.status = 'approved'
   ORDER BY b.booking_date DESC LIMIT 1
   Display as Bootstrap progress bar and status badges.

4. QUICK LINKS — buttons: Book a Service → booking/create.php, View Invoices, Download My Files.

STYLE:
- Light professional theme (white with gold accent).
- Bootstrap 5. Mobile responsive.
```

---

## PAGE 10 — CLIENT BOOKING FORM (`client/booking_create.php`)

```
Build a Booking Submission Form in native PHP called client/booking_create.php.

ACCESS CONTROL: Include auth/client_guard.php.

LAYOUT:
- Multi-step form using Bootstrap tabs or a single-page form.

FORM FIELDS:
- Select Package (dropdown — fetch from packages table WHERE status='active')
- Event Type (text — e.g., Wedding, Graduation, Birthday)
- Preferred Booking Date (date input — must be a future date)
- Venue / Location (text)
- Additional Notes (textarea)

AVAILABILITY CHECK (AJAX or PHP POST):
Before allowing submission, check:
SELECT COUNT(*) FROM bookings WHERE booking_date = ? AND status = 'approved'
If count >= max_bookings_per_day (set to 3 by default), show: "This date is fully booked. Please choose another date."

ON SUCCESSFUL SUBMISSION:
INSERT INTO bookings (client_id, package_id, booking_date, event_type, venue, notes, status)
VALUES (?, ?, ?, ?, ?, ?, 'pending')
Then INSERT INTO post_production (booking_id) VALUES (LAST_INSERT_ID()) to initialize the tracker.
Send email notification to admin using PHP mail().
Show success message: "Your booking request has been submitted. Please wait for admin approval."

VALIDATION (PHP server-side):
- All fields required.
- booking_date must be > today.
- package_id must exist in packages table.
```

---

## PAGE 11 — CLIENT BOOKINGS HISTORY (`client/my_bookings.php`)

```
Build a My Bookings page in native PHP called client/my_bookings.php.

ACCESS CONTROL: Include auth/client_guard.php.

FEATURES:

1. BOOKINGS TABLE — show all bookings for the logged-in client:
   SELECT b.id, p.name AS package, b.booking_date, b.event_type, b.venue, b.status, b.created_at
   FROM bookings b
   JOIN packages p ON b.package_id = p.id
   WHERE b.client_id = ?
   ORDER BY b.created_at DESC

   Columns: #, Package, Date, Event Type, Venue, Status (Bootstrap badge), Actions.

2. STATUS BADGES:
   - pending → yellow
   - approved → green
   - rescheduled → blue
   - cancelled → red

3. CANCEL REQUEST BUTTON — only show if status = 'pending' or 'approved':
   Clicking shows a confirmation modal with a reason textarea.
   On confirm: INSERT INTO cancellations (booking_id, client_id, reason, deposit_amount, deposit_retained) with deposit_retained = 0 initially.
   UPDATE bookings SET status='cancelled' WHERE id=? AND client_id=?

4. VIEW DETAILS BUTTON — opens modal showing full booking info + current post-production status.

PHP LOGIC: PDO prepared statements. All queries filter by $_SESSION['user_id'] to ensure data isolation.
```

---

## PAGE 12 — CLIENT POST-PRODUCTION STATUS (`client/post_production_status.php`)

```
Build a Post-Production Status page in native PHP called client/post_production_status.php.

ACCESS CONTROL: Include auth/client_guard.php.

PURPOSE: Allow the client to see the editing and delivery progress of their photos, videos, and other outputs — without needing to message the studio.

FEATURES:

1. PROJECT CARDS — one card per approved booking with post-production data:
   SELECT pp.*, b.booking_date, b.event_type, p.name AS package
   FROM post_production pp
   JOIN bookings b ON pp.booking_id = b.id
   JOIN packages p ON b.package_id = p.id
   WHERE b.client_id = ? AND b.status = 'approved'
   ORDER BY b.booking_date DESC

   Each card shows:
   - Package name and Event date
   - Photo Editing Status (badge)
   - Video Production Status (badge)
   - Overall Progress (Bootstrap progress bar using progress_percent)
   - Deadline and Deadline Status badge (early/near/late)
   - Notes from the admin (if any)

2. DOWNLOAD FILES BUTTON — only show if drive_link is not empty:
   <a href="<?= htmlspecialchars($row['drive_link']) ?>" target="_blank" class="btn btn-success">Access My Files</a>

3. STATUS EXPLANATION — small info box explaining what each status means (not started / in progress / completed).

PHP LOGIC: PDO. Filter strictly by client_id from session.
```

---

## PAGE 13 — CLIENT INVOICES (`client/invoices.php`)

```
Build a Client Invoices page in native PHP called client/invoices.php.

ACCESS CONTROL: Include auth/client_guard.php.

DATABASE TABLE NEEDED:
CREATE TABLE invoices (
  id INT AUTO_INCREMENT PRIMARY KEY,
  booking_id INT,
  client_id INT,
  amount DECIMAL(10,2),
  deposit_paid DECIMAL(10,2),
  balance DECIMAL(10,2),
  issued_date DATE,
  status ENUM('unpaid','partial','paid') DEFAULT 'unpaid',
  FOREIGN KEY (booking_id) REFERENCES bookings(id),
  FOREIGN KEY (client_id) REFERENCES users(id)
);

FEATURES:

1. INVOICES TABLE:
   SELECT i.*, p.name AS package, b.booking_date, b.event_type
   FROM invoices i
   JOIN bookings b ON i.booking_id = b.id
   JOIN packages p ON b.package_id = p.id
   WHERE i.client_id = ?
   ORDER BY i.issued_date DESC

   Columns: Invoice #, Package, Event Date, Amount, Deposit Paid, Balance, Status (badge), Download.

2. DOWNLOAD INVOICE BUTTON — links to invoice_pdf.php?id=X which generates a printable HTML invoice:
   - Studio header (Harvy Mance Films logo, address, contact)
   - Invoice details (client name, booking info, amount breakdown)
   - Print button using window.print() in JavaScript
   - Clean print CSS: @media print { .no-print { display: none; } }

3. STATUS BADGES:
   - unpaid → red
   - partial → yellow
   - paid → green
```

---

## PAGE 14 — ADMIN REPORTS (`admin/reports.php`)

```
Build a Reports Page in native PHP called admin/reports.php.

ACCESS CONTROL: Include auth/admin_guard.php.

FEATURES:

1. FILTER BAR — filter by month/year (dropdown or date range input).
   Default to current month.

2. BOOKING SUMMARY TABLE:
   SELECT u.name AS client, p.name AS package, b.booking_date, b.event_type, b.status
   FROM bookings b
   JOIN users u ON b.client_id = u.id
   JOIN packages p ON b.package_id = p.id
   WHERE MONTH(b.booking_date) = ? AND YEAR(b.booking_date) = ?
   ORDER BY b.booking_date ASC

   Columns: Client, Package, Date, Event Type, Status.
   Show status count summary above the table:
   Approved: X | Pending: X | Cancelled: X

3. CANCELLATION SUMMARY — table of cancellations for the selected period with deposit info.

4. POST-PRODUCTION COMPLETION RATE:
   SELECT
     COUNT(*) AS total,
     SUM(CASE WHEN progress_percent = 100 THEN 1 ELSE 0 END) AS completed
   FROM post_production pp
   JOIN bookings b ON pp.booking_id = b.id
   WHERE MONTH(b.booking_date) = ? AND YEAR(b.booking_date) = ?

5. EXPORT TO CSV BUTTON — PHP generates a downloadable CSV of the booking summary:
   header('Content-Type: text/csv');
   header('Content-Disposition: attachment; filename="report_[month].csv"');
   Output each booking row using fputcsv().
```

---

## PAGE 15 — ADMIN CLIENT ACCOUNT MANAGEMENT (`admin/clients.php`)

```
Build a Client Account Management page in native PHP called admin/clients.php.

ACCESS CONTROL: Include auth/admin_guard.php.

FEATURES:

1. CLIENT LIST TABLE:
   SELECT id, name, email, status, created_at FROM users WHERE role='client' ORDER BY created_at DESC
   Columns: ID, Name, Email, Status, Date Registered, Actions (Edit, Deactivate, View Bookings).

2. CREATE CLIENT ACCOUNT — modal form: Name, Email, Temporary Password.
   Hash password: password_hash($password, PASSWORD_DEFAULT)
   INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'client')
   Send login credentials to client via PHP mail().

3. EDIT CLIENT — modal to update name and email.
   UPDATE users SET name=?, email=? WHERE id=? AND role='client'

4. RESET PASSWORD — generate a random 8-character password, hash it, update the DB, and email it.
   $new_pass = bin2hex(random_bytes(4));

5. DEACTIVATE / REACTIVATE:
   UPDATE users SET status='inactive' WHERE id=?
   Inactive clients cannot log in (checked in login.php query: WHERE status='active').

6. VIEW CLIENT BOOKINGS — link to admin/bookings.php?client_id=X to filter bookings by client.
```

---

## SHARED FILES TO BUILD

```
Build the following shared utility files used across all pages:

1. config/db.php — PDO MySQL connection:
<?php
$host = 'localhost';
$db = 'harvy_mance_films';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';
$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC];
try { $pdo = new PDO($dsn, $user, $pass, $options); }
catch (PDOException $e) { die('DB Connection failed: ' . $e->getMessage()); }

2. auth/admin_guard.php:
<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
  header('Location: ../login.php'); exit;
}

3. auth/client_guard.php:
<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'client') {
  header('Location: ../login.php'); exit;
}

4. includes/admin_sidebar.php — reusable sidebar HTML with Bootstrap nav links.

5. includes/admin_topbar.php — reusable topbar with welcome message and logout.

6. includes/client_sidebar.php — reusable sidebar for client portal.

7. logout.php:
<?php
session_start();
session_destroy();
header('Location: login.php'); exit;

8. assets/css/style.css — custom styles shared across all pages.
```

---

## FULL DATABASE SCHEMA SUMMARY

```sql
-- Run this in MySQL to create all tables for the system

CREATE DATABASE IF NOT EXISTS harvy_mance_films;
USE harvy_mance_films;

CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(100) UNIQUE NOT NULL,
  password VARCHAR(255) NOT NULL,
  role ENUM('admin','client') DEFAULT 'client',
  status ENUM('active','inactive') DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE packages (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  description TEXT,
  price DECIMAL(10,2),
  inclusions TEXT,
  status ENUM('active','archived') DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE bookings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  client_id INT,
  package_id INT,
  booking_date DATE,
  event_type VARCHAR(100),
  venue VARCHAR(255),
  notes TEXT,
  status ENUM('pending','approved','rescheduled','cancelled') DEFAULT 'pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (client_id) REFERENCES users(id),
  FOREIGN KEY (package_id) REFERENCES packages(id)
);

CREATE TABLE staff (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100),
  role VARCHAR(100),
  email VARCHAR(100),
  phone VARCHAR(20),
  status ENUM('active','inactive') DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE staff_schedules (
  id INT AUTO_INCREMENT PRIMARY KEY,
  staff_id INT,
  booking_id INT,
  booking_date DATE,
  FOREIGN KEY (staff_id) REFERENCES staff(id),
  FOREIGN KEY (booking_id) REFERENCES bookings(id)
);

CREATE TABLE cancellations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  booking_id INT,
  client_id INT,
  reason TEXT,
  deposit_amount DECIMAL(10,2),
  deposit_retained DECIMAL(10,2),
  cancelled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (booking_id) REFERENCES bookings(id),
  FOREIGN KEY (client_id) REFERENCES users(id)
);

CREATE TABLE post_production (
  id INT AUTO_INCREMENT PRIMARY KEY,
  booking_id INT,
  photo_status ENUM('not_started','in_progress','completed') DEFAULT 'not_started',
  video_status ENUM('not_started','in_progress','completed') DEFAULT 'not_started',
  other_status ENUM('not_started','in_progress','completed') DEFAULT 'not_started',
  progress_percent INT DEFAULT 0,
  deadline DATE,
  deadline_status ENUM('early','near','late') DEFAULT 'early',
  notes TEXT,
  drive_link VARCHAR(500),
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (booking_id) REFERENCES bookings(id)
);

CREATE TABLE invoices (
  id INT AUTO_INCREMENT PRIMARY KEY,
  booking_id INT,
  client_id INT,
  amount DECIMAL(10,2),
  deposit_paid DECIMAL(10,2),
  balance DECIMAL(10,2),
  issued_date DATE,
  status ENUM('unpaid','partial','paid') DEFAULT 'unpaid',
  FOREIGN KEY (booking_id) REFERENCES bookings(id),
  FOREIGN KEY (client_id) REFERENCES users(id)
);

-- Default admin account (password: admin123)
INSERT INTO users (name, email, password, role) VALUES
('Admin', 'admin@harvymancefilms.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');
```

---

*15 pages + shared files + full database schema | Native PHP + MySQL + Bootstrap 5*
