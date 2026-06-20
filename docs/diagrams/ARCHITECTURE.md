# Smart RT — Architecture Diagrams

> Render these diagrams in any Mermaid-compatible viewer (VS Code Mermaid extension, GitHub, GitLab, etc.)

---

## 1. High-Level Architecture

```mermaid
graph TB
    subgraph Users["Users"]
        Warga["Warga - Residents"]
        Pengurus["Pengurus - Board"]
    end

    subgraph Portal["Portal Warga - Public No Login"]
        PHome["/ Home"]
        PVerify["/cek-nomor"]
        PRonda["/jadwal-ronda"]
        PCheckin["/checkin-ronda"]
        PScan["/scan-iuran"]
        PAnnounce["/pengumuman"]
        PReport["/lapor"]
        PLetter["/surat"]
        PVotes["/voting"]
    end

    subgraph Dashboard["Dashboard Pengurus - Login + Role Gate"]
        DIndex["/dashboard"]
        DHousehold["/dashboard/rumah"]
        DResident["/dashboard/warga"]
        DRonda["/dashboard/ronda"]
        DScan["/dashboard/sesi-scan"]
        DDenda["/dashboard/denda"]
        DKas["/dashboard/kas"]
        DAnnounce["/dashboard/pengumuman"]
        DReport["/dashboard/laporan"]
        DLetter["/dashboard/surat"]
        DVotes["/dashboard/voting"]
        DInventory["/dashboard/inventaris"]
    end

    subgraph Services["Service Layer"]
        ResidentLookup["ResidentLookup"]
        RondaCheckin["RondaCheckin"]
        IuranScan["IuranScan"]
        DendaService["DendaService"]
        KasReport["KasReport"]
        TransactionCorrection["TransactionCorrection"]
        VotingService["VotingService"]
        PinGate["PinGate"]
        ScanOfficerGate["ScanOfficerGate"]
        AdminDashboardSummary["AdminDashboardSummary"]
    end

    subgraph Support["Support Classes"]
        PhoneNumber["PhoneNumber"]
        Audit["Audit"]
        QrCode["QrCode"]
        AnnouncementHtml["AnnouncementHtml"]
    end

    subgraph Models["Eloquent Models"]
        User["User"]
        Household["Household"]
        Resident["Resident"]
        RondaSchedule["RondaSchedule"]
        RondaAssignment["RondaAssignment"]
        RondaScanSession["RondaScanSession"]
        CashTransaction["CashTransaction"]
        Announcement["Announcement"]
        Report["Report"]
        LetterRequest["LetterRequest"]
        Vote["Vote"]
        VoteOption["VoteOption"]
        VoteBallot["VoteBallot"]
        InventoryItem["InventoryItem"]
        AuditLog["AuditLog"]
    end

    subgraph DB["MariaDB 11.8"]
        TUsers["users"]
        THouseholds["households"]
        TResidents["residents"]
        TRondaSchedules["ronda_schedules"]
        TRondaAssignments["ronda_assignments"]
        TRondaScanSessions["ronda_scan_sessions"]
        TCashTransactions["cash_transactions"]
        TAnnouncements["announcements"]
        TReports["reports"]
        TLetterRequests["letter_requests"]
        TVotes["votes"]
        TVoteOptions["vote_options"]
        TVoteBallots["vote_ballots"]
        TInventoryItems["inventory_items"]
        TAuditLogs["audit_logs"]
    end

    Warga --> Portal
    Pengurus --> Dashboard
    Portal --> Services
    Dashboard --> Services
    Services --> Models
    Support --> Services
    Models --> DB

    style Portal fill:#ecfdf5,stroke:#059669,stroke-width:2px
    style Dashboard fill:#eff6ff,stroke:#2563eb,stroke-width:2px
    style Services fill:#fefce8,stroke:#ca8a04,stroke-width:2px
    style Support fill:#fdf4ff,stroke:#9333ea,stroke-width:2px
    style DB fill:#fef2f2,stroke:#dc2626,stroke-width:2px
```

---

## 2. Entity-Relationship Diagram

```mermaid
erDiagram
    users {
        bigint id PK
        varchar name
        varchar email UK
        varchar password
        varchar role
        timestamps created_at
        timestamps updated_at
    }

    households {
        bigint id PK
        varchar house_number
        text address
        varchar head_name
        varchar qr_token UK
        boolean is_active
        timestamps created_at
        timestamps updated_at
    }

    residents {
        bigint id PK
        bigint household_id FK
        varchar name
        varchar phone
        boolean is_active
        text ronda_notes
        timestamps created_at
        timestamps updated_at
    }

    ronda_schedules {
        bigint id PK
        date date UK
        text notes
        bigint created_by FK
        timestamps created_at
        timestamps updated_at
    }

    ronda_assignments {
        bigint id PK
        bigint ronda_schedule_id FK
        bigint resident_id FK
        timestamp checked_in_at
    }

    ronda_scan_sessions {
        bigint id PK
        date date UK
        varchar pin
        timestamp starts_at
        timestamp ends_at
        bigint created_by FK
        timestamps created_at
        timestamps updated_at
    }

    cash_transactions {
        bigint id PK
        date date
        bigint household_id FK
        bigint resident_id FK
        bigint ronda_scan_session_id FK
        varchar type
        bigint amount
        varchar status
        varchar source
        bigint recorded_by FK
        text reason
        bigint reverses_id FK
        timestamp cancelled_at
        bigint cancelled_by FK
        timestamps created_at
        timestamps updated_at
    }

    announcements {
        bigint id PK
        varchar title
        text body
        boolean is_published
        timestamp published_at
        bigint created_by FK
        timestamps created_at
        timestamps updated_at
    }

    reports {
        bigint id PK
        varchar phone
        bigint resident_id FK
        varchar category
        text description
        varchar status
        text notes
        timestamps created_at
        timestamps updated_at
    }

    letter_requests {
        bigint id PK
        varchar phone
        bigint resident_id FK
        varchar type
        text purpose
        varchar status
        text notes
        timestamps created_at
        timestamps updated_at
    }

    votes {
        bigint id PK
        text question
        varchar status
        timestamp starts_at
        timestamp ends_at
        bigint created_by FK
        timestamps created_at
        timestamps updated_at
    }

    vote_options {
        bigint id PK
        bigint vote_id FK
        varchar label
    }

    vote_ballots {
        bigint id PK
        bigint vote_id FK
        bigint vote_option_id FK
        bigint resident_id FK
        varchar phone
    }

    inventory_items {
        bigint id PK
        varchar name
        varchar condition
        varchar status
        varchar location
        varchar holder
        text notes
        timestamps created_at
        timestamps updated_at
    }

    audit_logs {
        bigint id PK
        bigint actor_id FK
        varchar action
        varchar subject_type
        bigint subject_id
        json metadata
        varchar ip_address
        varchar user_agent
        timestamps created_at
        timestamps updated_at
    }

    %% Relationships
    users ||--o{ households : "manages"
    households ||--o{ residents : "has many"
    users ||--o{ ronda_schedules : "creates"
    ronda_schedules ||--o{ ronda_assignments : "has many"
    residents ||--o{ ronda_assignments : "assigned to"
    users ||--o{ ronda_scan_sessions : "creates"
    ronda_scan_sessions ||--o{ cash_transactions : "gates"
    households ||--o{ cash_transactions : "pays"
    residents ||--o{ cash_transactions : "recorded for"
    users ||--o{ cash_transactions : "records"
    cash_transactions }o--o| cash_transactions : "reverses"
    users ||--o{ announcements : "creates"
    residents ||--o{ reports : "submits"
    residents ||--o{ letter_requests : "requests"
    users ||--o{ votes : "creates"
    votes ||--o{ vote_options : "has options"
    votes ||--o{ vote_ballots : "has ballots"
    vote_options ||--o{ vote_ballots : "receives votes"
    residents ||--o{ vote_ballots : "casts"
    users ||--o{ audit_logs : "actor"
```

---

## 3. Data Flow — Kas and Ronda

```mermaid
sequenceDiagram
    autonumber
    participant Admin as Admin RT
    participant Dashboard as Dashboard
    participant Portal as Portal Warga
    participant Services as Services
    participant DB as MariaDB
    participant Warga as Warga (Phone)

    Note over Admin,DB: Setup Phase (Dashboard)

    Admin->>Dashboard: Create ronda schedule for date D
    Dashboard->>Services: RondaSchedule.create(date)
    Services->>DB: INSERT ronda_schedules

    Admin->>Dashboard: Assign officers to schedule
    Dashboard->>Services: RondaAssignment.create(schedule, resident)
    Services->>DB: INSERT ronda_assignments

    Admin->>Dashboard: Create scan session (PIN + time window)
    Dashboard->>Services: RondaScanSession.create(date, pin, starts_at, ends_at)
    Services->>DB: INSERT ronda_scan_sessions

    Note over Warga,DB: Evening - Officers Check In

    Warga->>Portal: POST /checkin-ronda (phone)
    Portal->>Services: RondaCheckin.execute(phone)
    Services->>DB: SELECT residents WHERE phone = ? AND is_active
    Services->>DB: SELECT ronda_schedules WHERE date = today
    Services->>DB: UPDATE ronda_assignments SET checked_in_at = NOW()
    Services-->>Warga: Check-in successful

    Note over Warga,DB: Evening - Collect Iuran (Rp500 per house)

    Warga->>Portal: POST /scan-iuran (QR token + PIN)
    Portal->>Services: PinGate.validate(pin)
    Services->>DB: SELECT ronda_scan_sessions WHERE pin = ? AND active window
    Services->>Services: ScanOfficerGate.validate(phone)
    Services->>DB: SELECT ronda_assignments WHERE checked_in_at IS NOT NULL
    Services->>Services: IuranScan.execute(qr_token, session, officer)
    Services->>DB: SELECT households WHERE qr_token = ?
    Services->>DB: SELECT cash_transactions WHERE household_id AND date (dedup check)
    Services->>DB: INSERT cash_transactions (Rp500, iuran_harian)
    Services->>DB: INSERT audit_logs
    Services-->>Warga: Rp500 recorded

    Note over Admin,DB: Next Day - Review and Fine (Dashboard)

    Admin->>Dashboard: View denda page
    Dashboard->>Services: DendaService.findCandidates(date)
    Services->>DB: SELECT ronda_assignments LEFT JOIN cash_transactions WHERE checked_in_at IS NULL
    Services-->>Dashboard: List of un-checkined officers
    Admin->>Dashboard: Confirm fine for officer X
    Dashboard->>Services: DendaService.recordFine(resident, date)
    Services->>DB: INSERT cash_transactions (Rp5.000, denda)
    Services->>DB: INSERT audit_logs
    Services-->>Admin: Fine recorded

    Note over Admin,DB: Anytime - Recap

    Admin->>Dashboard: View kas recap
    Dashboard->>Services: KasReport.summary(date, range)
    Services->>DB: Aggregate cash_transactions
    Services-->>Dashboard: Daily/weekly/monthly totals + unpaid list
```

---

## 4. Data Flow — Resident Portal Actions

```mermaid
sequenceDiagram
    autonumber
    participant Warga as Warga
    participant Portal as Portal Warga
    participant Lookup as ResidentLookup
    participant Service as Domain Service
    participant DB as MariaDB

    Note over Warga,DB: All portal actions start with phone lookup

    Warga->>Portal: Enter phone number
    Portal->>Lookup: resolve(phone)
    Lookup->>DB: SELECT residents WHERE phone = ? AND is_active
    Lookup-->>Portal: PhoneLookupResult(resident, message)

    alt Resident found
        Portal->>Service: Execute action with resident context
        alt Action type is check-in
            Service->>DB: UPDATE ronda_assignments SET checked_in_at = NOW()
        else Action type is report
            Service->>DB: INSERT reports (resident_id, category, description)
        else Action type is letter request
            Service->>DB: INSERT letter_requests (resident_id, type, purpose)
        else Action type is vote
            Service->>DB: INSERT vote_ballots (vote_id, resident_id, phone)
        end
        Service-->>Portal: Result DTO (ok, message)
        Portal-->>Warga: Success or error feedback
    else Resident not found
        Portal-->>Warga: Phone not registered
    end
```

---

## 5. Route Map

```mermaid
graph LR
    subgraph Public["Public Routes (No Auth)"]
        HOME["/ portal.home"]
        VERIFY["/cek-nomor portal.verify"]
        RONDA["/jadwal-ronda portal.ronda (feature:ronda)"]
        CHECKIN["/checkin-ronda portal.checkin (feature:ronda)"]
        SCAN["/scan-iuran portal.scan (feature:kas)"]
        ANNOUNCE["/pengumuman portal.announcements (feature:announcements)"]
        REPORT["/lapor portal.report (feature:reports)"]
        LETTER["/surat portal.letter (feature:letters)"]
        VOTE_LIST["/voting portal.votes (feature:voting)"]
        VOTE_DETAIL["/voting id portal.vote (feature:voting)"]
    end

    subgraph Auth["Auth Route"]
        LOGIN["/login auth.login"]
    end

    subgraph DashboardRoutes["Dashboard Routes (auth + pengurus middleware)"]
        DASH["/dashboard dashboard.index"]
        HH["/dashboard/rumah households.index"]
        HHQR["/dashboard/rumah id qr households.qr"]
        RES["/dashboard/warga residents.index"]
        SETTINGS["/dashboard/pengaturan settings.index (admin_rt only)"]
        RONDA_MGR["/dashboard/ronda ronda.index (feature:ronda)"]
        RONDA_DETAIL["/dashboard/ronda id ronda.show (feature:ronda)"]
        SCAN_MGR["/dashboard/sesi-scan scan.index (feature:ronda)"]
        DENDA["/dashboard/denda denda.index (feature:ronda)"]
        KAS["/dashboard/kas kas.index (feature:kas)"]
        KAS_TX["/dashboard/kas/transaksi kas.transactions (feature:kas)"]
        ANN_MGR["/dashboard/pengumuman announcements.index (feature:announcements)"]
        REPORT_MGR["/dashboard/laporan reports.index (feature:reports)"]
        LETTER_MGR["/dashboard/surat letters.index (feature:letters)"]
        VOTE_MGR["/dashboard/voting votes.index (feature:voting)"]
        VOTE_RESULT["/dashboard/voting id votes.show (feature:voting)"]
        INV["/dashboard/inventaris inventory.index (feature:inventory)"]
    end

    style Public fill:#ecfdf5,stroke:#059669
    style Auth fill:#fefce8,stroke:#ca8a04
    style DashboardRoutes fill:#eff6ff,stroke:#2563eb
```

---

## 6. Technology Stack

```mermaid
graph TB
    subgraph Frontend["Frontend"]
        Vite["Vite"]
        TailwindCSS["Tailwind CSS"]
        AlpineJS["Alpine.js"]
        Livewire["Livewire 4 + Volt"]
        PWA["PWA manifest + SW"]
    end

    subgraph Backend["Backend"]
        Laravel["Laravel 12"]
        PHP["PHP 8.4"]
        Eloquent["Eloquent ORM"]
        ServicesSL["Service Layer"]
        SupportSL["Support Classes"]
    end

    subgraph Data["Data Layer"]
        MariaDB["MariaDB 11.8"]
        Cache["Laravel Cache"]
        Queue["Laravel Jobs"]
    end

    subgraph DevTools["Dev Tools"]
        DDEV["DDEV"]
        Pest["Pest TDD"]
        Git["Git"]
        MCP["MCP Servers"]
    end

    Frontend --> Backend
    Backend --> Data
    DevTools --> Backend

    style Frontend fill:#ecfdf5,stroke:#059669
    style Backend fill:#eff6ff,stroke:#2563eb
    style Data fill:#fef2f2,stroke:#dc2626
    style DevTools fill:#fdf4ff,stroke:#9333ea
```

---

*Generated for Smart RT — 2026-06-18*
