# API Documentation

## Access Token

To interact with the API you must first generate a personal access token. These endpoints require a user session (login) but no existing token.

### Generate a key

```bash
curl -X POST http://localhost/api/user/api-key -b cookies.txt -c cookies.txt
```

**Response**
```json
{ "api_key": "YOUR_TOKEN" }
```

### Retrieve current key

```bash
curl http://localhost/api/user/api-key -b cookies.txt
```

**Response**
```json
{ "api_key": "YOUR_TOKEN" }
```

All subsequent requests must send the header:

```
Authorization: Bearer YOUR_TOKEN
```

## Endpoints

### List organizations
`GET /api/organizations`

```bash
curl -H "Authorization: Bearer YOUR_TOKEN" http://localhost/api/organizations
```

**Response**
```json
{
  "organizations": [
    {
      "id": 1,
      "nombre_organizacion": "Org 1",
      "descripcion": "..."
    }
  ]
}
```

### Organization details
`GET /api/organizations/{id}`
```bash
curl -H "Authorization: Bearer YOUR_TOKEN" http://localhost/api/organizations/1
```

**Response**
```json
{
  "organization": {
    "id": 1,
    "nombre_organizacion": "Org 1",
    "groups": []
  }
}
```

### Group containers
`GET /api/groups/{group}/containers`

```bash
curl -H "Authorization: Bearer YOUR_TOKEN" http://localhost/api/groups/1/containers
```

**Response**
```json
{
  "containers": [
    {
      "id": 3,
      "name": "My Container",
      "description": "...",
      "created_at": "01/09/2025 12:00",
      "meetings_count": 1,
      "is_company": true,
      "group_name": "Marketing"
    }
  ]
}
```

### Content containers
`GET /api/content-containers`

```bash
curl -H "Authorization: Bearer YOUR_TOKEN" http://localhost/api/content-containers
```

**Response**
```json
{
  "success": true,
  "containers": [
    {
      "id": 7,
      "name": "My Container",
      "description": "...",
      "created_at": "01/09/2025 12:00",
      "meetings_count": 2,
      "is_company": false,
      "group_name": null
    }
  ]
}
```

### Meetings in a content container
`GET /api/content-containers/{id}/meetings`

```bash
curl -H "Authorization: Bearer YOUR_TOKEN" http://localhost/api/content-containers/7/meetings
```

**Response**
```json
{
  "success": true,
  "container": {
    "id": 7,
    "name": "My Container",
    "description": "...",
    "is_company": false,
    "group_name": null
  },
  "meetings": [
    {
      "id": 25,
      "meeting_name": "Weekly Sync",
      "created_at": "05/09/2025 10:00",
      "audio_drive_id": "abc123",
      "transcript_drive_id": "def456",
      "audio_folder": "2025-09-05",
      "transcript_folder": "Transcripts",
      "has_transcript": true
    }
  ]
}
```

### List meetings
`GET /api/meetings`

```bash
curl -H "Authorization: Bearer YOUR_TOKEN" http://localhost/api/meetings
```

**Response**
```json
{
  "success": true,
  "meetings": [
    {
      "id": 25,
      "meeting_name": "Free Meeting",
      "created_at": "05/09/2025 10:00",
      "audio_folder": "folderA",
      "transcript_folder": "Base de datos",
      "is_legacy": false,
      "source": "meetings"
    }
  ]
}
```

## Errors

- **403 Forbidden** – The access token is missing, invalid, or you lack permissions.
- **404 Not Found** – The requested resource does not exist.
