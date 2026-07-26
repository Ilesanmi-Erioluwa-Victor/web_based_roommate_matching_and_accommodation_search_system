# RoomieMatch

Web-based Roommate Matching and Accommodation Search System

## Tech Stack

- PHP 8.2+
- MongoDB Atlas
- Cloudinary (image upload)
- Brevo (email)
- Vanilla HTML/CSS/JS

## Setup

1. Clone and install dependencies:
```bash
composer install
```

2. Copy `.env.example` to `.env` and fill in your credentials:
```bash
cp .env.example .env
```

3. Set up MongoDB Atlas cluster with 2dsphere indexes:
```bash
# Run the setup endpoint after deploying:
curl -X POST https://your-app.com/api/setup/ensure-indexes
```

4. Create an admin user:
```bash
curl -X POST https://your-app.com/api/setup/create-admin \
  -H "Content-Type: application/json" \
  -d '{"name":"Admin","email":"admin@example.com","password":"your-password"}'
```

5. Run locally:
```bash
composer start
# or
php -S localhost:8000 -t public
```

## Features

- User registration & login with JWT
- Lifestyle-based roommate matching algorithm
- Accommodation listing with Cloudinary photos
- Geospatial search by location/radius
- Connection request system (anti-spam)
- Real-time messaging
- Reviews & ratings
- Reporting & admin moderation
- Email notifications via Brevo

## API Endpoints

See full API route list in the project documentation.

## Project Structure

```
roomiematch/
  public/       - Entry point, assets (CSS/JS)
  src/
    config/     - DB connection, env
    models/     - User, Listing, Connection, Message, Review, Report
    controllers/- Route handlers
    services/   - CompatibilityEngine, EmailService, CloudinaryService
    middleware/  - Auth, RateLimit
    views/      - HTML templates
  vendor/       - Composer packages
```
