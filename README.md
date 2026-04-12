# CertVerify - Blockchain-Inspired Certificate Validation System
## File Structure

certverify/
├── index.php              # Login page
├── dashboard.php          # Admin/Institution dashboard
├── issue_certificate.php  # Issue new certificate
├── verify.php             # Public certificate verification page
├── certificates.php       # View all certificates
├── logout.php             # Logout
├── config/
│   └── db.php             # Database connection
├── includes/
│   ├── header.php         # Common header/nav
│   └── footer.php         # Common footer
├── actions/
│   ├── issue_action.php   # Handle certificate issuance
│   └── verify_action.php  # Handle verification
├── css/
│   └── style.css          # Main stylesheet
└── sql/
    └── database.sql       # Database setup SQL

## Setup Instructions
1. Import sql/database.sql into your MySQL database
2. Edit config/db.php with your DB credentials
3. Place the certverify/ folder in your web server root (e.g. htdocs or www)
4. Visit http://localhost/certverify/
5. Default admin login: admin@certverify.com / admin123
