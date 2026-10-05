# LechGo — System Architecture

> **LechGo** is a custom PHP MVC web platform for the Philippine lechon (roasted pig) industry.  
> It connects the full pork supply chain — from pig farming to lechon delivery — in a single multi-role marketplace.

---

## Table of Contents
1. [High-Level Overview](#1-high-level-overview)
2. [Architecture Diagram](#2-architecture-diagram)
3. [Technology Stack](#3-technology-stack)
4. [Application Layers](#4-application-layers)
5. [User Roles](#5-user-roles)
6. [Module Breakdown](#6-module-breakdown)
7. [Database Schema Summary](#7-database-schema-summary)
8. [External Services](#8-external-services)
9. [Authentication & Security](#9-authentication--security)
10. [Core Business Flow](#10-core-business-flow)

---

## 1. High-Level Overview

LechGo is a **server-side rendered (SSR) PHP application** with no external framework (no Laravel/Symfony). It uses a **Front Controller pattern** — all HTTP requests are routed through a single `index.php` entry point. The platform is hosted on shared hosting (InfinityFree / Apache + MariaDB).

It operates as both:
- **B2C Marketplace** — customers browse lechon listings, place orders, pay online
- **B2B Supply Chain Tool** — livestock owners manage farms, hire staff, order feed from suppliers, track pig inventory

---

## 2. Architecture Diagram

```
┌────────────────────────────────────────────────────────────────────┐
│                          CLIENT (Browser)                          │
│         HTML/CSS/JS  ·  AJAX (Fetch API)  ·  budget-planner.js    │
└─────────────────────────────┬──────────────────────────────────────┘
                              │ HTTP Requests
                              ▼
┌────────────────────────────────────────────────────────────────────┐
│                    APACHE / .htaccess                              │
│  mod_rewrite: all URIs  ──►  index.php?__route=<path>             │
│  (Direct access allowed for: api_*.php standalone files)          │
└─────────────────────────────┬──────────────────────────────────────┘
                              │
                              ▼
┌────────────────────────────────────────────────────────────────────┐
│                   FRONT CONTROLLER  (index.php)                   │
│                                                                    │
│  1. Bootstrap                                                      │
│     ├── config/db.php        — MySQLi connection                   │
│     ├── config/email.php     — SMTP constants                      │
│     ├── config/roles.php     — RBAC role/permission definitions    │
│     └── Load models, controllers, services                         │
│                                                                    │
│  2. Session & CSRF Init                                            │
│     └── new Session()  →  new RBACMiddleware()                    │
│                                                                    │
│  3. Route Resolution                                               │
│     ├── From $_GET['__route']  (fallback)                          │
│     └── From REQUEST_URI      (clean URLs)                         │
│                                                                    │
│  4. Pre-RBAC API Routes (bypass auth check)                        │
│     ├── api/budget-planner/*                                       │
│     ├── api/market-intelligence/*                                  │
│     ├── notifications                                              │
│     └── submit-review                                              │
│                                                                    │
│  5. RBAC Gate                                                      │
│     ├── isProtectedRoute()?  →  check session                      │
│     └── hasRoutePermission(role, route)?  →  redirect if denied   │
│                                                                    │
│  6. Route Dispatch  (switch $route)                                │
│     ├── require VIEW FILE  (GET pages)                             │
│     ├── Inline POST handlers  (most CRUD logic)                    │
│     └── Controller method calls                                    │
└──────┬───────────────────────────────────────────────┬────────────┘
       │ Renders Views                                 │ Calls Services
       ▼                                               ▼
┌─────────────────┐   ┌───────────────────────────────────────────────┐
│  VIEW LAYER     │   │  SERVICE / EXTERNAL LAYER                     │
│  resources/     │   │                                               │
│  views/         │   │  ┌──────────────────────────────────────┐    │
│                 │   │  │  PayMongoService.php                  │    │
│  layouts/       │   │  │  - createCheckoutSession             │    │
│  auth/          │   │  │  - createPaymentIntent               │    │
│  admin/         │   │  │  - attachPaymentMethod               │    │
│  customer/      │   │  │  - verifyWebhookSignature            │    │
│  lechonero/     │   │  └──────────────┬───────────────────────┘    │
│  livestock-     │   │                 │                             │
│   owner/        │   │  ┌──────────────▼───────────────────────┐    │
│  pig_caretaker/ │   │  │  EmailService.php (PHPMailer/Gmail)   │    │
│  supplier/      │   │  │  - sendVerificationEmail             │    │
│  logistics/     │   │  │  - sendOTPEmail                      │    │
│  feed-          │   │  └──────────────────────────────────────┘    │
│   distributor/  │   │                                               │
│  pig-slaughter/ │   │  ┌──────────────────────────────────────┐    │
│  market-        │   │  │  MarketTrendsService.php (NewsAPI)    │    │
│   intelligence/ │   │  │  - fetchTrends (PHP/agriculture news) │    │
│  role-          │   │  │  - categorize by impact level         │    │
│   application/  │   │  │  - cache in DB (60-min refresh)       │    │
│  employee/      │   │  └──────────────────────────────────────┘    │
└─────────────────┘   └───────────────────────────────────────────────┘
       │                           │
       └──────────┬────────────────┘
                  ▼
┌────────────────────────────────────────────────────────────────────┐
│                      DATA LAYER                                    │
│                                                                    │
│  MySQLi (MariaDB 11.4)       app/models/                          │
│                                                                    │
│  ┌────────────┐ ┌──────────────┐ ┌────────────────┐              │
│  │ Auth/Users │ │ Pig/Farm Ops │ │  Feed Supply   │              │
│  │  users     │ │  pig_pins    │ │  feed_products │              │
│  │  otp_verif │ │  pig_details │ │  feed_inventory│              │
│  │  email_tok │ │  swine       │ │  feeding_sched │              │
│  │  role_apps │ │  hogs_market │ │  feed_orders   │              │
│  │  emp_apps  │ │  inventory_  │ │  transaction_  │              │
│  │  emp_assgn │ │   logs       │ │   logs         │              │
│  └────────────┘ └──────────────┘ └────────────────┘              │
│  ┌────────────┐ ┌──────────────┐ ┌────────────────┐              │
│  │Lechon Order│ │   Payments   │ │Market Intel    │              │
│  │  lechon_   │ │  customer_   │ │  market_trends │              │
│  │   listings │ │   payments   │ │  pinned_trends │              │
│  │  lechon_   │ │  order_total │ │  calendar_items│              │
│  │   orders   │ │   _cost      │ │  market_notes  │              │
│  │  cooking_  │ │  receipts    │ │                │              │
│  │   schedule │ │              │ │                │              │
│  │  delivery_ │ │              │ │                │              │
│  │   status   │ │              │ │                │              │
│  └────────────┘ └──────────────┘ └────────────────┘              │
│                                                                    │
│  Other: notifications · reviews · budget_planner · locations      │
│         calendar_items · caretaker_reports · workflow_state_log   │
└────────────────────────────────────────────────────────────────────┘

         ┌────────────────────────────────────────────────┐
         │         STANDALONE API FILES  (bypass index)   │
         │  api_psa_pig_stats.php   — PSA gov't pig data  │
         │  api_market_intelligence_news.php  — NewsAPI   │
         │  api_approve_application.php  — admin actions  │
         │  api_reject_application.php   — admin actions  │
         │  api_force_logout_user.php    — admin control  │
         └────────────────────────────────────────────────┘
```

---

## 3. Technology Stack

| Layer | Technology |
|-------|------------|
| Language | PHP 7.2+ |
| Web Server | Apache (InfinityFree shared hosting) |
| Database | MariaDB 11.4 (MySQLi driver, raw parameterized queries) |
| URL Routing | `.htaccess` mod_rewrite → `index.php` (Front Controller) |
| Templating | Plain PHP files (no Blade/Twig) |
| Session | PHP native sessions (`$_SESSION`), 24h lifetime |
| Email | PHPMailer 7 via Gmail SMTP (`smtp.gmail.com:587`, STARTTLS) |
| Payments | PayMongo (checkout sessions, payment intents, webhooks) |
| Market News | NewsAPI (fetches Philippine pork/lechon agriculture articles) |
| Gov't Data | PSA (Philippine Statistics Authority) pig production API |
| Auth | Google OAuth (google/apiclient — for Google login) |
| Package Manager | Composer (phpmailer/phpmailer, google/apiclient) |
| Frontend JS | Vanilla JS / Fetch API, budget-planner.js |

---

## 4. Application Layers

### Entry Point & Routing
- **`.htaccess`** — Apache rewrite rules forward all requests to `index.php`. Also sets security headers (X-Frame-Options, X-XSS-Protection, CSP).
- **`index.php`** — 2000+ line front controller. Handles: boot sequence, session init, pre-RBAC API routes, RBAC gate, route dispatch via `switch ($route)`.

### Middleware
| File | Purpose |
|------|---------|
| `app/middleware/Session.php` | Session lifecycle (start, CSRF generation, get/set user, logout). Auto-syncs user role from DB every 2 seconds so role changes take effect immediately without re-login. |
| `app/middleware/RBACMiddleware.php` | `canAccess(route)`, `authorize(route)`, `isProtectedRoute(route)`, role-based dashboard redirects. Public routes are explicitly whitelisted. |

### Config
| File | Purpose |
|------|---------|
| `config/db.php` | MySQLi connection; returns `$conn` |
| `config/email.php` | SMTP host/port/user/pass constants |
| `config/roles.php` | All 9 role keys, `ROLE_PERMISSIONS` (route wildcards), `ROLE_DASHBOARDS`, helper functions (`hasRoutePermission`, `routeMatchesPermission`) |

### Controllers (`app/controllers/`)
Fully implemented: `AuthController`, `RoleApplicationController`, `EmployeeApplicationController`, `SiteAdministrationController`, `MarketIntelligenceController`, `NotificationController`, `PaymentController`, `BudgetPlannerController`, `LocationController`, `ReviewController`.

Empty stubs (logic lives inline in `index.php`): `OrderController`, `SupplierController`, `DeliveryController`, `ReservationController`, `CookingController`, `SwineController`, `ScheduleController`, `DashboardController`.

### Models (`app/models/`)
30 model classes. Each wraps MySQLi calls for its table. No ORM — all queries use `prepare`/`bind_param`. Key models: `User`, `Lechonero`, `LivestockOwner`, `PigCaretaker`, `FeedSupplier`, `FeedDistributor`, `Order`, `Payment`, `Swine`, `Notification`, `Review`, `BudgetPlanner`, `MarketTrend`, `RoleApplication`, `EmployeeApplication`, `EmployeeAssignment`.

### Services (`app/services/`)
| Service | Responsibility |
|---------|---------------|
| `EmailService.php` | Wraps PHPMailer — sends verification emails and OTP codes via Gmail SMTP |
| `PayMongoService.php` | Creates checkout sessions and payment intents via PayMongo REST API; validates webhooks |
| `MarketTrendsService.php` | Fetches Philippine pork market news from NewsAPI, categorizes articles, caches in `market_trends` table |

### Views (`resources/views/`)
Plain PHP templates. Layouts are composed manually via `include` of `layouts/dashboard-layout.php` and `layouts/sidebar.php` (sidebar is role-aware).

---

## 5. User Roles

The system has **9 roles**, all sharing the same `users` table (role stored as an enum column).

```
┌──────────────────┬──────────────────────────────────────────────────────────────┐
│  Role            │  What They Can Do                                            │
├──────────────────┼──────────────────────────────────────────────────────────────┤
│ admin            │ Manage all users, approve/reject role applications,           │
│                  │ view system logs, force logout users                          │
├──────────────────┼──────────────────────────────────────────────────────────────┤
│ customer         │ Browse lechon listings, order lechon, buy live pigs,         │
│                  │ track orders, pay online (PayMongo/GCash/COD),               │
│                  │ write reviews, use the Budget Planner, apply for jobs        │
├──────────────────┼──────────────────────────────────────────────────────────────┤
│ lechonero        │ View assigned lechon orders, manage cooking schedules,       │
│                  │ update cooking/quality status, manage profile                │
├──────────────────┼──────────────────────────────────────────────────────────────┤
│ livestock_owner  │ Manage farm (site administration), hire/assign employees,    │
│                  │ post pig market listings, order feed from suppliers,         │
│                  │ track inventory & production costs, view market intelligence │
├──────────────────┼──────────────────────────────────────────────────────────────┤
│ pig_caretaker    │ Track pig inventory per pen, record feedings, manage feed    │
│                  │ inventory, receive feed orders, write caretaker reports      │
├──────────────────┼──────────────────────────────────────────────────────────────┤
│ supplier         │ Manage feed product catalog, fulfill livestock owner orders, │
│                  │ view transaction logs, sell on feeds market                  │
├──────────────────┼──────────────────────────────────────────────────────────────┤
│ feed_distributor │ Manage distributor product inventory, fulfill orders,        │
│                  │ view market listings                                          │
├──────────────────┼──────────────────────────────────────────────────────────────┤
│ logistics        │ View delivery assignments, update delivery status,           │
│                  │ upload delivery/payment photos                               │
├──────────────────┼──────────────────────────────────────────────────────────────┤
│ pig_slaughter    │ View live pigs available for slaughter, manage profile       │
└──────────────────┴──────────────────────────────────────────────────────────────┘
```

**Role Acquisition:**
- All users register as `customer` by default.
- Apply for `livestock_owner` via `RoleApplicationController` (piggery application form + document uploads).
- Get assigned `pig_caretaker`, `lechonero`, `logistics`, etc. by a `livestock_owner` via `SiteAdministrationController` (employee application → assignment).
- `admin` is set directly in the database.

---

## 6. Module Breakdown

### Authentication Module
- Register → Email verification (token, 24h) → Login → OTP verification (6-digit, 5min) → Session
- Complete profile step after first login
- Google OAuth login (via `google/apiclient`)
- CSRF protection on all POST forms

### Order & Lechon Pipeline
```
Customer browses lechon_listings
    → Places order (lechon_orders)
    → Selects add-ons (laman-loob, boopes, dinuguan)
    → Chooses delivery/pickup + delivery address
    → Pays via PayMongo / GCash / COD
    → Lechonero assigned to cook (cooking_schedule)
    → Cooking status updated (lechon_status)
    → Logistics driver delivers (delivery_status)
    → Customer reviews the order (reviews)
```

### Feed Supply Chain
```
Livestock Owner orders feed
    → Browses feed_products (from supplier/distributor catalogs)
    → Places livestock_feed_orders
    → Supplier confirms & processes
    → Feed Distributor fulfills delivery
    → Livestock Owner receives → logged in transaction_logs
    → Pig Caretaker adds to feed_inventory
    → Records individual feedings in feeding_schedule
```

### Pig Inventory Management
- Pig Caretaker maintains `pig_pins` (pens/enclosures) and `pig_details` (individual pigs)
- Supports weight calculator (heart girth + body length formula)
- Inventory snapshots for historical tracking
- Caretaker reports sent to Livestock Owner

### Market Intelligence
- Fetches Philippine pork/lechon news from NewsAPI (cached 60 min)
- PSA gov't pig production stats via `api_psa_pig_stats.php`
- Users can pin trends, add calendar notes, and write market notes
- Available to: `livestock_owner` role

### Budget Planner
- Customers plan holiday budgets (Christmas, New Year, Birthdays)
- Planned items (JSON) vs. actual spending tracked
- Calculates over/under-spend percentage
- Available to: `customer` role, served via `api/budget-planner/*` AJAX API

### Site Administration (Livestock Owner)
- Livestock owner manages their farm's employee roster
- Reviews employee applications (`employee_applications`)
- Assigns roles via `employee_assignments`
- Real-time role change propagation via Session DB-sync

### Notifications
- `notifications` table stores per-user messages
- `NotificationController` serves unread count and list as JSON
- Polled by frontend on authenticated pages

### Payments
- PayMongo integration: checkout sessions (card, GCash), payment intents
- Supports full payment and 50% down payment with remaining balance
- Webhook handler updates payment status in `customer_payments` and `order_total_cost`
- COD (Cash on Delivery) as offline payment option
- Payment photos uploaded by logistics driver

---

## 7. Database Schema Summary

**~40 tables** organized by domain:

| Domain | Key Tables |
|--------|-----------|
| Users & Auth | `users`, `otp_verification`, `email_verification_tokens` |
| Roles & Employment | `role_applications`, `requirements`, `employee_applications`, `employee_assignments` |
| Business Profiles | `lechoneros`, `livestock_owners`, `pig_caretakers`, `suppliers`, `feed_distributors` |
| Pig & Farm | `pig_pins`, `pig_details`, `swine`, `swine_inventory`, `swine_inventory_snapshots`, `hogs_market`, `inventory_logs` |
| Feed Supply Chain | `feed_inventory`, `feeding_schedule`, `feed_products`, `livestock_feed_orders`, `livestock_feed_order_items`, `feed_distributor_products`, `feed_distributor_orders`, `transaction_logs` |
| Lechon Orders | `lechon_listings`, `lechon_orders`, `lechon_status`, `orders`, `order_total_cost`, `cooking_schedule`, `delivery_status` |
| Payments | `customer_payments`, `receipts_record` |
| Market Intel | `market_trends`, `pinned_trends`, `calendar_items`, `market_notes` |
| Customer Tools | `budget_planner`, `reviews`, `notifications` |
| Utility | `locations`, `caretaker_reports`, `workflow_state_log` |

---

## 8. External Services

```
┌─────────────────────────────────────────────────────────────────────┐
│  External Service         │  Used For                               │
├───────────────────────────┼─────────────────────────────────────────┤
│  PayMongo                 │  Card/GCash online payments             │
│  (api.paymongo.com)       │  Checkout sessions, payment intents,    │
│                           │  webhook event verification              │
├───────────────────────────┼─────────────────────────────────────────┤
│  Gmail SMTP               │  Transactional email                    │
│  (smtp.gmail.com:587)     │  Email verification tokens, OTP codes   │
│                           │  Via PHPMailer with App Password         │
├───────────────────────────┼─────────────────────────────────────────┤
│  NewsAPI                  │  Market intelligence news feed          │
│  (newsapi.org)            │  Fetches PH pork/lechon news articles   │
│                           │  Cached in DB for 60 minutes            │
├───────────────────────────┼─────────────────────────────────────────┤
│  PSA (gov.ph)             │  Philippine pig production statistics   │
│                           │  via api_psa_pig_stats.php              │
├───────────────────────────┼─────────────────────────────────────────┤
│  Google OAuth             │  Social login (Google account sign-in)  │
│  (google/apiclient)       │                                         │
└───────────────────────────┴─────────────────────────────────────────┘
```

---

## 9. Authentication & Security

### Authentication Flow
```
Register ──► Email Verification (24h token)
               ├── Verified → Proceed to login
               └── Expired  → Resend token

Login ──────► OTP via Email (6-digit, 5-min expiry)
               ├── Max 5 attempts before 1-hour lockout
               ├── Correct → Session::setUser() → Dashboard
               └── Expired → Resend OTP
```

### Session Security
- Sessions last 24 hours (`session.cookie_lifetime = 86400`)
- `httponly` cookies (no JS access)
- `SameSite=Lax` (allows PayMongo redirect callbacks)
- `Secure` flag set if HTTPS is active
- CSRF token (`bin2hex(random_bytes(32))`) in `$_SESSION['csrf_token']`, verified on all POST

### Role-Change Propagation
- `Session::getUser()` re-queries `users` table every **2 seconds**
- A role approved by admin takes effect on the user's very next page load — no re-login needed

### RBAC
- Route permissions defined per role in `config/roles.php` with wildcard support (`customer/*`)
- Public routes are whitelisted in `RBACMiddleware::isProtectedRoute()`
- Unauthorized access → redirect to `/home` with flash error

### Passwords
- Bcrypt hashing (`password_hash` / `password_verify`)
- Minimum requirements: 8 characters, 1 uppercase, 1 number

---

## 10. Core Business Flow

```
┌─────────────────────────────────────────────────────────────────────┐
│                    LECHGO CORE BUSINESS FLOW                        │
└─────────────────────────────────────────────────────────────────────┘

[Feed Supplier / Feed Distributor]
  │  Publishes feed products to catalog
  ▼
[Livestock Owner]
  │  Orders feed  ──────────────────────────────────► [Feed Supplier fulfills]
  │  Receives feed → logs transaction
  │  Assigns Pig Caretaker to farm
  ▼
[Pig Caretaker]
  │  Records feed in inventory
  │  Tracks individual pigs (weight, health, pen)
  │  Records daily feedings
  │  Sends caretaker reports to Livestock Owner
  ▼
[Livestock Owner]
  │  Lists lechon products on marketplace (lechon_listings)
  │  Also sells live pigs (hogs_market)
  │  Monitors market intelligence (PSA stats + news)
  ▼
[Customer]
  │  Browses lechon listings
  │  Places order → selects add-ons → chooses delivery/pickup
  │  Pays online (PayMongo / GCash) or COD
  │  Uses Budget Planner to plan holiday spend
  ▼
[Lechonero]
  │  Receives order assignment
  │  Logs cooking schedule (start time, method, weight)
  │  Updates cooking quality status (temperature, skin, tenderness)
  ▼
[Logistics Driver]
  │  Receives delivery assignment
  │  Updates delivery status
  │  Uploads delivery + payment photos
  ▼
[Customer]
  │  Receives lechon ✓
  └  Writes review
```

---

*Architecture document auto-generated from codebase analysis — September 2026*
