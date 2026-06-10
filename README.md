# COREHOUR SYSTEM ⚡

**The ultimate execution ledger for engineers balancing full-time careers and side-hustle empires.**

CoreHour is a custom-built, dynamic momentum tracker designed to gamify deep work, penalize distractions, and manage weekly objectives through a strict, data-driven interface.

### 🌐 Live Demo

Experience the live application here: **[https://corehour.chandankrv.com](https://corehour.chandankrv.com)**

**Demo Access Credentials:**

* **Email:** `demo@corehour.com`
* **Password:** `demo123`

---

## 🎯 Core Features

* **Dynamic Score Engine:** Track your momentum. The custom algorithm rewards deep work (positive multipliers) and penalizes distractions (negative multipliers) to generate a true daily performance score out of 10.
* **Weekly Objectives:** Stop drifting. Plan your week, categorize your targets, and mark them complete as you crush them.
* **Execution Ledger:** A minimalist 24-hour note-taking system to log daily friction, wins, and technical roadblocks.
* **Data Sovereignty:** Your data belongs to you. Instantly export your entire execution ledger to CSV for custom Python/Pandas analytics.
* **EAV Database Architecture:** Highly scalable Entity-Attribute-Value structure allowing infinite customization of tracking fields without altering the database schema.

---

## 🛠️ Tech Stack

* **Backend:** Core PHP (8.x)
* **Database:** MySQL (PDO API for secure, prepared statements)
* **Frontend:** HTML5, Bootstrap 5, CSS3
* **Icons:** Bootstrap Icons
* **Security:** Password Hashing (Bcrypt), Native PHP Mail (OTP verification)

---

## 🚀 Local Installation & Setup

Follow these steps to deploy the CoreHour environment on your local machine using XAMPP, MAMP, or any standard LAMP/LEMP stack.

### 1. Clone the Repository

```bash
git clone [https://github.com/yourusername/corehour.git](https://github.com/yourusername/corehour.git)
cd corehour
```
3. Initialize the Database
We have provided a single 1-click installation script that builds the entire schema and populates it with 60 days of demo data.

Locate the schema_and_demo.sql file in the project directory.

Import or run this entire SQL file against your new database.

This script automatically creates the Demo User (demo@corehour.com / demo123).

4. Run the Application
Ensure your local PHP server is running.

Navigate to http://localhost/corehour/login.php in your browser.

Use the demo credentials to log in, or click "Create Account" to initialize a fresh workspace.

🔒 Security Measures
Strict Access Control: All internal pages enforce session-based authentication.

Demo Mode Restrictions: Destructive actions (deletions, profile modifications, password resets) are hard-locked for User ID 1 (the Demo Account) to preserve the integrity of the public portfolio.

Timezone Sync: OTPs utilize strict server-time matching to prevent expiration drift across global servers.

👨‍💻 Author
Designed & Developed with ❤️, code, and creativity by Chandan Kumar.

Email: contact2ckv@gmail.com

© 2026 Chandan Kumar. All rights reserved.