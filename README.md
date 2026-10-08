# Magento 2 Shipment Tracking

[![CI](https://github.com/ahsanhafeez1122-ship-it/magento2-shipment-tracking/actions/workflows/ci.yml/badge.svg)](https://github.com/ahsanhafeez1122-ship-it/magento2-shipment-tracking/actions/workflows/ci.yml)
![Magento 2.4](https://img.shields.io/badge/Magento-2.4.x-orange)
![PHP 8.1+](https://img.shields.io/badge/PHP-8.1%2B-777bb4)
![License: MIT](https://img.shields.io/badge/license-MIT-green)

Clickable tracking links for UK carriers in shipment emails and the customer account, live
delivery status from a signed webhook, and a CLI audit that finds tracks customers can't follow.

## The problem it solves

Out of the box, Magento only links tracking numbers for the carriers it integrates with.
Warehouses that ship with DPD, Royal Mail or Evri usually save tracks as **"Custom"** with a
free-text title, so customers get a shipment email with a bare number and no link. Then they
contact support to ask where their parcel is.

## Features

**Tracking links everywhere**
- Shipment email: each parcel gets a **Track parcel** button
- Customer account › My Orders › order: a "Track your parcels" table with live status
- Carrier recognised from the carrier code **or the free-text title**:
  `custom` + "DPD Local Next Day" → DPD Local (longest alias wins, so "DPD Local" beats "DPD")
- Old brand names as aliases (Hermes → Evri)
- Numbers cleaned before building the link: `jd 0002 1234` → `JD00021234`, safely URL-encoded
- `{postcode}` placeholder for carriers that need it

**Carriers in admin, not in code**

One line per carrier under **Stores › Configuration › Sales › Shipment Tracking**:

```
code | Label | https://url/{number} | optional number regex | optional aliases
dpd  | DPD   | https://track.dpd.co.uk/parcels/{number} | ^\d{14}$ | dpd uk, dpd local
```

Ships with DPD, Royal Mail, Evri, Parcelforce, DHL, UPS, FedEx and Yodel. Invalid lines are reported
with the line number and skipped, so a typo never breaks the other carriers.

**Delivery status webhook**
- `POST /shipment-tracking/webhook/receive` for your tracking provider
- **HMAC-SHA256 signature** with a signed timestamp (`t=…,v1=…`, same scheme as Stripe), so
  forged and replayed requests are refused. Multiple `v1` values allow secret rotation
- Secret stored encrypted in config
- **Idempotent**: each provider event id is stored once (unique key), so retries never duplicate
- **Out-of-order safe**: an older "in transit" can't overwrite "out for delivery", and
  "delivered" is final
- Carrier wording normalised: "Delivered to neighbour", `OUT_FOR_DELIVERY`, "failed-attempt", …

**Audit command**

```bash
bin/magento tracking:audit --days=30
```

```
+----------+----------+--------------+-----------------+----------------+--------------------------------------+
| Track ID | Order ID | Carrier code | Title           | Number         | Problem                              |
+----------+----------+--------------+-----------------+----------------+--------------------------------------+
| 9812     | 40211    | custom       | Bobs Van        | 88812          | Carrier not recognised: add an alias |
| 9790     | 40177    | custom       | Royal Mail 24   | AB12345678GB   | Number does not match carrier format |
+----------+----------+--------------+-----------------+----------------+--------------------------------------+
```

Exits non-zero when problems are found, so it can run in cron or CI and alert someone.

## Webhook format

```http
POST /shipment-tracking/webhook/receive
Content-Type: application/json
X-Tracking-Signature: t=1791400000,v1=5f2b…   (hex HMAC-SHA256 of "1791400000.<raw body>")

{
  "event_id": "evt_01J9ZK…",
  "tracking_number": "15501234567890",
  "carrier": "dpd",
  "status": "OUT_FOR_DELIVERY",
  "description": "With your driver",
  "location": "Birmingham",
  "occurred_at": "2026-10-07T09:30:00Z"
}
```

| Response | Meaning |
|---|---|
| `200 {"result":"applied"}` | Stored, and the parcel's status moved forward |
| `200 {"result":"stored"}` | Stored in history; the status already reflects a later event |
| `200 {"result":"duplicate"}` | Already received; nothing changed |
| `200 {"result":"unknown_track"}` | No shipment with that number (logged) |
| `401` | Bad or expired signature |
| `422` | Payload invalid; the message says which field |

Signing in PHP:

```php
$t = time();
$signature = hash_hmac('sha256', $t . '.' . $body, $secret);
$header = "t={$t},v1={$signature}";
```

## Installation

```bash
composer require ahsanhafeez/module-shipment-tracking
# or copy the repo to app/code/AhsanHafeez/ShipmentTracking

bin/magento module:enable AhsanHafeez_ShipmentTracking
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento cache:flush
```

Adds two nullable columns to `sales_shipment_track` (`delivery_status`, `delivery_status_at`)
and one table, `ahsan_tracking_event`, for the event history.

## Code structure

```
Model/Carrier/             Carrier, config parser, resolver: plain PHP
Model/Status/              Delivery statuses and the "should this replace that" rule
Model/Webhook/             Signature verifier and payload parser: plain PHP
Model/TrackLinkResolver    Magento track → link, label and status
Service/EventProcessor     Idempotent, transactional event storage
Controller/Webhook/Receive Signed endpoint (CSRF-exempt, HMAC instead)
Console/Command/Audit…     bin/magento tracking:audit
ViewModel/Tracking         Data for the email and customer account templates
view/frontend/             Email track template, customer order page block
Test/Unit/                 30 PHPUnit tests
```

## Tests

```bash
php Test/static-check.php   # PHP/PHTML parse, XML well-formed
phpunit                     # 30 tests: carrier parsing/matching, URLs, statuses, signatures, payloads
```

CI runs on PHP 8.1, 8.2 and 8.3.

## License

MIT, © Ahsan Hafeez
