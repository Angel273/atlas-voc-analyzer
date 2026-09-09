# ATLAS VOC Analysis

**ATLAS VOC Analysis** is an enterprise-grade, security-first Voice of Customer (VOC) analytical web application built for the **Atlas Tools** ecosystem. It delivers deep analytical capabilities (NPS, CSAT, Professionalism, Survey Volume, Verbatim Categorization, and Predictive Forecasting) within an editorial, clean, and decision-focused interface strictly aligned with `design.md`.

---

## Architectural Invariants & Guarantees

1. **AiGateway Enforcement**: No controller, job, or domain service ever calls an LLM directly. All AI interactions flow through `App\Services\Ai\Gateway\AiGateway` using an abstract `AiProvider` interface (`GeminiProvider` / `MockAiProvider`).
2. **Privacy Gateway & Scoped Pseudonymization**: No real identities (agent BMS, supervisor names, employee IDs, customer PII) ever reach an external LLM. User prompts and tool arguments are pseudonymized *before* provider invocation using non-sequential random tokens (`AGT_...`, `SUP_...`, `REC_...`).
3. **Permission-Gated Re-Identification**: Model outputs are re-identified only upon delivery to users holding the `identity.view` permission. Users without this permission see the opaque pseudonyms.
4. **Dual Transcript Architecture**: Chat sessions persist two distinct representations:
   - **Display Transcript**: Authorized conversation text presented to the user.
   - **AI Transcript**: Scoped, pseudonymized transcript provided as context to the model.
5. **Deterministic Query & Formula Engines**: The LLM *never* writes raw SQL or computes statistical forecasts. It interacts solely through an allowlisted, validated **Query DSL** and predefined tools. Calculations run through the same unified engine across Dashboard, Chat, and Forecast.
6. **Tamper-Evident Audit Ledger**: Every operational event, data import, and AI step is recorded in an audit trail with canonical payload hashing and chained SHA-256 hashes (`previous_hash`, `event_hash`).
7. **Idempotent Data Import**: Ingestion uses `survey_id` as natural key + record hashing to prevent accidental duplication or silent overrides, keeping track of import batches, templates, and mapping transformations.

---

## Tech Stack

- **Backend**: Laravel 13, PHP 8.4 / 8.5
- **Frontend**: Inertia.js, React 19, TypeScript, Tailwind CSS, ECharts, Lucide Icons
- **Database**: PostgreSQL (Production) / SQLite (Local testing)
- **Queues & Sessions**: Laravel Database Queues & Database Sessions
- **Excel Ingestion**: PhpSpreadsheet
- **AI Provider**: Google Gemini API (gemini-2.5-flash) via `AiGateway`
- **Design System**: Atlas Tools (`--ink: #18221d`, `--paper: #f7f6f1`, `--sage: #dce4d8`, `--lime: #d7f45b`, `--line: #ccd1ca`, `--muted: #687169`, fonts `DM Sans` + `Instrument Serif`)

---

## Local Development & Setup

### 1. Prerequisites
- PHP >= 8.3 with `pdo_sqlite`, `pdo_pgsql`, `intl`, `gd`, `zip`
- Composer >= 2
- Node.js >= 18 and NPM

### 2. Installation
```sh
git clone <repo-url> "ATLAS VOC ANALYZER"
cd "ATLAS VOC ANALYZER"

composer install
npm install --legacy-peer-deps
npm run build
```

### 3. Environment & Migrations
```sh
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
```

### 4. Running the Application
In separate terminal tabs:
```sh
# Tab 1: Web server
php artisan serve

# Tab 2: Queue worker (for verbatim categorization & imports)
php artisan queue:work --queue=default,imports,categorization

# Tab 3 (optional, for frontend hot-reload):
npm run dev
```

The application will be accessible at: `http://localhost:8000`.

---

## Default Test Credentials

| Role | Email | Password | Permissions |
| :--- | :--- | :--- | :--- |
| **Administrator** | `admin@atlas.local` | `password123` | Full access (Superadmin) |
| **Analyst** | `analyst@atlas.local` | `password123` | `data.*`, `dashboard.*`, `ai.*`, `forecast.*`, `identity.view`, `categories.manage`, `audit.view` |
| **Viewer** | `viewer@atlas.local` | `password123` | `data.view`, `dashboard.view`, `ai.chat`, `forecast.view` (no `identity.view`) |

---

## CLI Utilities

### Generate Sample VOC Excel
Generates a realistic multi-sheet Excel file with custom headers and 60 surveys:
```sh
php artisan atlas:sample-excel sample_voc_data.xlsx
```

### Import Sample VOC Data
Executes end-to-end import with percentage transformations and queues AI verbatim categorization:
```sh
php artisan atlas:import-sample sample_voc_data.xlsx
php artisan queue:work --stop-when-empty
```

---

## Automated Test Suite

Run the full suite of 25 unit and feature tests:
```sh
php artisan test
```

Tests cover:
- **Unit**:
  - `QueryDslValidatorTest`: Disallowed metrics, dimensions, operators, limits.
  - `FormulaEngineTest`: Arithmetic precedence, safe functions, division by zero, non-eval safety.
  - `PseudonymServiceTest`: Non-sequential random tokens, scoped idempotency.
  - `PiiScrubberTest`: Redaction of emails, phones, BMS IDs, supervisor names.
  - `ForecastEngineTest`: OLS Linear Trend slope/intercept, SMA, EMA, MAE, RMSE, $R^2$.
  - `TamperEvidenceTest`: Chained SHA-256 hash validation and tamper detection.
- **Feature**:
  - `AuthTest`: Login, rate limiting (5 attempts), permission check middleware.
  - `ExcelImportFlowTest`: Percentage transformations, required attributes, idempotency, and versioning.
  - `AiGatewayPrivacyTest`: Pre-pseudonymization and `identity.view` permission check.
  - `HealthCheckTest`: `/health` response format and database connectivity check.

---

## Production Deployment (Railway)

1. Connect your Git repository to **Railway**.
2. Add a **PostgreSQL** service in Railway.
3. Link the web service to the Dockerfile.
4. Configure the following environment variables in Railway:
   - `APP_ENV=production`
   - `APP_KEY=base64:...` (generate via `php artisan key:generate --show`)
   - `DB_CONNECTION=pgsql`
   - `DB_HOST=${{Postgres.PGHOST}}`
   - `DB_PORT=${{Postgres.PGPORT}}`
   - `DB_DATABASE=${{Postgres.PGDATABASE}}`
   - `DB_USERNAME=${{Postgres.PGUSER}}`
   - `DB_PASSWORD=${{Postgres.PGPASSWORD}}`
   - `SESSION_DRIVER=database`
   - `QUEUE_CONNECTION=database`
   - `CACHE_STORE=database`
   - `GEMINI_API_KEY=your-gemini-api-key`
5. Railway will automatically build the image using `Dockerfile`, execute migrations on startup, and monitor service health via `/health`.
6. Add a second service in Railway for the worker using the `Procfile` command:
   ```sh
   php artisan queue:work --queue=default,imports,categorization --tries=3
   ```

---

## License

Confidential & Proprietary — Atlas Tools Ecosystem.
