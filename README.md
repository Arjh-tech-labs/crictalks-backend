# Setup Project

- `php artisan composer install`
- update database credentails
```bash
DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=
DB_USERNAME=
DB_PASSWORD=
```
- `php artisan migrate`

If you get any issue due to migration then update `.env` file

```bash
DB_COLLATION=<Your DB COLLATION> # Goto: DB >> Operations >> Table options >> Collation: Default
```

# Firebase and YouTube Integration

This document provides information on how to integrate Firebase Authentication and YouTube Live Streaming with the Cricket Scoring CRM.

## Firebase Authentication

### Setup

1. Create a Firebase project at https://console.firebase.google.com/
2. Enable Phone Authentication
3. Download the Firebase Admin SDK service account key
4. Save it to `storage/app/firebase/sportsvaniapp-01-firebase-adminsdk.json`
5. Update your `.env` file with Firebase configuration:
