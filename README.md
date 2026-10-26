# GoldFlow AI

GoldFlow AI is a portfolio project exploring reliable gold-purchase order processing, backend engineering best practices, and AI-assisted operational investigations.

The project is being developed incrementally using Laravel and Vue.js, with an emphasis on correctness, testing, security, and maintainable architecture.

> **Project status:** In development. This is an educational project, not a live trading or investment platform. Payment processing and AI operations features may be simulated or planned rather than fully implemented.

## Technology Stack

- **Backend:** PHP 8.2+, Laravel 12
- **Frontend:** Vue 3, TypeScript, Inertia.js
- **Styling:** Tailwind CSS
- **Database:** MySQL
- **Build tooling:** Vite, npm
- **Testing:** Pest / PHPUnit
- **Code quality:** Laravel Pint, ESLint, Prettier
- **Version control and CI:** Git, GitHub Actions

## Current Development Focus

- Gold price records and price snapshots
- Gold purchase order data model and relationships
- Order creation service and validation
- Idempotency to help prevent duplicate order creation
- Database migrations and automated tests
- Authentication and application scaffolding
- Automated frontend and backend quality checks

Features will be documented as they are implemented and tested.

## Planned Work

- Reliable transaction and concurrency handling
- Payment simulation, webhook handling, and reconciliation
- Queues, retries, and background processing
- Audit logging and operational observability
- AI-assisted incident investigation with controlled tool access
- Approval workflows, safety controls, and deployment preparation

## Local Development Setup

### Prerequisites

Install the following:

- PHP 8.2 or compatible version supported by the project
- Composer
- Node.js version specified in `.nvmrc`
- npm
- MySQL

### Installation

Clone the repository and enter the project directory:

```bash
git clone https://github.com/sajidsajad/goldflow-ai.git
cd goldflow-ai
```

Install backend and frontend dependencies:

```bash
composer install
npm ci
```

Create your local environment file:

```bash
cp .env.example .env
```

Configure your local MySQL database and credentials in `.env`. Then generate the application key:

```bash
php artisan key:generate
```

Run database migrations:

```bash
php artisan migrate
```

Build the frontend assets:

```bash
npm run build
```

Start the Laravel development server:

```bash
php artisan serve
```

For frontend development with hot reloading, run this in a separate terminal:

```bash
npm run dev
```

## Testing and Quality Checks

Run the backend tests:

```bash
php artisan test
```

Build the frontend:

```bash
npm run build
```

Check frontend formatting:

```bash
npm run format:check
```

Run frontend linting:

```bash
npm run lint
```

Run Laravel Pint to check or format PHP code according to the project's configured rules:

```bash
vendor/bin/pint
```

## Security Notes

- Never commit `.env`, database credentials, API keys, or other secrets.
- Keep local configuration separate from `.env.example`.
- Use simulated payments while developing unless a properly secured integration is intentionally configured.
- Do not use this educational project to make real investment or trading decisions.

## License

No license has been selected yet. All rights are reserved unless and until a license is added to this repository.
