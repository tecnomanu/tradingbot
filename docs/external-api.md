# External API — TradingBot

REST API for external agents and MCP integrations to query and control bots.

## Authentication

Every request must include the API key in **one** of these ways:

```
X-API-Key: <your-key>
```
or
```
Authorization: Bearer <your-key>
```

The key is set via `EXTERNAL_API_KEY` in `.env`.  
Generate a new one: `openssl rand -hex 32`

---

## Base URL

```
http://<host>/api/v1/
```

---

## Endpoints

### Status / Overview

#### `GET /api/v1/status`

Global snapshot: active bots, order totals, PNL, Redis health.

**Response:**
```json
{
  "success": true,
  "data": {
    "timestamp": "2026-03-06T01:00:00+00:00",
    "bots": {
      "total": 3,
      "active": 1,
      "stopped": 2,
      "error": 0,
      "active_list": [
        {
          "id": 6,
          "name": "BTC Tight Grid",
          "symbol": "BTCUSDT",
          "total_pnl": 13.02,
          "open_orders": 15,
          "is_testnet": true,
          "started_at": "2026-03-01T19:08:42+00:00"
        }
      ]
    },
    "orders": {
      "open": 15,
      "filled": 24,
      "filled_24h": 0,
      "total_pnl": 13.02
    },
    "horizon": {
      "redis_up": true,
      "redis_version": "7.2.0"
    }
  }
}
```

---

### Bots

#### `GET /api/v1/bots`

List all bots with their current status and metrics.

**Response:** Array of bot objects (see schema below).

---

#### `GET /api/v1/bots/{id}`

Full detail of a single bot: config, all orders grouped by status, PNL history.

**Response:**
```json
{
  "success": true,
  "data": {
    "bot": { /* bot object */ },
    "config": {
      "grid_levels": [67979, 68513, ...],
      "quantity_per_grid": 0.00295,
      "real_investment": 1000.0,
      "additional_margin": 0.0,
      "profit_per_grid": 0.53
    },
    "orders": {
      "open": [ /* order objects */ ],
      "filled": [ /* order objects */ ],
      "cancelled": []
    },
    "pnl_history": [ /* snapshots */ ]
  }
}
```

---

#### `PATCH /api/v1/bots/{id}`

Update bot configuration. **Bot must be stopped.**

**Body (all fields optional):**
```json
{
  "name": "New name",
  "price_lower": 65000,
  "price_upper": 78000,
  "grid_count": 20,
  "investment": 1500,
  "leverage": 5,
  "slippage": 0.5,
  "stop_loss_price": 63000,
  "take_profit_price": 80000
}
```

---

#### `POST /api/v1/bots/{id}/start`

Start a stopped/pending bot. Dispatches `InitializeBotJob` via Horizon.

**Response:**
```json
{
  "success": true,
  "message": "Bot start dispatched. Orders will be placed on Binance shortly.",
  "data": { "bot_id": 6, "status": "pending" }
}
```

---

#### `POST /api/v1/bots/{id}/stop`

Stop an active bot. Cancels all open orders on Binance synchronously.

**Response:**
```json
{
  "success": true,
  "message": "Bot stopped. All open orders cancelled.",
  "data": { "bot_id": 6, "status": "stopped" }
}
```

---

### Orders

#### `GET /api/v1/orders`

All orders across all bots (global view).

**Query params:**
| Param  | Values                  | Default |
|--------|-------------------------|---------|
| status | open, filled, cancelled | all     |
| limit  | 1–1000                  | 200     |

**Response:**
```json
{
  "success": true,
  "data": {
    "aggregate": {
      "total": 39,
      "open": 15,
      "filled": 24,
      "cancelled": 0,
      "total_pnl_usdt": 13.02
    },
    "filters": { "status": null, "limit": 200 },
    "orders": [
      {
        "id": 1,
        "side": "buy",
        "status": "open",
        "price": 67979.0,
        "quantity": 0.00295,
        "grid_level": 0,
        "pnl": 0.0,
        "binance_order_id": "123456789",
        "filled_at": null,
        "created_at": "2026-03-01T19:08:42+00:00",
        "bot": { "id": 6, "name": "BTC Tight Grid", "symbol": "BTCUSDT" }
      }
    ]
  }
}
```

---

#### `GET /api/v1/orders/bot/{id}`

Orders for a specific bot.

**Query params:**
| Param  | Values                  | Default |
|--------|-------------------------|---------|
| status | open, filled, cancelled | all     |
| side   | buy, sell               | all     |
| limit  | 1–500                   | 100     |

---

#### `GET /api/v1/orders/bot/{id}/stats`

Order statistics for a bot.

**Response:**
```json
{
  "success": true,
  "data": {
    "bot_id": 6,
    "open": 15,
    "filled": 22,
    "filled_24h": 3,
    "rounds_24h": 1,
    "buys": 11,
    "sells": 11,
    "pnl": {
      "total": 13.02,
      "best": 1.18,
      "worst": 0.0
    },
    "last_fill": {
      "price": 66473.8,
      "side": "sell",
      "filled_at": "2026-03-02T09:20:05+00:00"
    }
  }
}
```

---

## Bot Object Schema

```json
{
  "id": 6,
  "name": "BTC Tight Grid",
  "symbol": "BTCUSDT",
  "side": "neutral",
  "status": "active",
  "account": "Testnet BTC",
  "is_testnet": true,
  "price_lower": 67979.0,
  "price_upper": 75107.0,
  "grid_count": 15,
  "investment": 3000.0,
  "real_investment": 1000.0,
  "leverage": 3,
  "stop_loss_price": null,
  "take_profit_price": null,
  "total_pnl": 13.02,
  "grid_profit": 13.02,
  "trend_pnl": 0.0,
  "pnl_pct": 1.3,
  "total_rounds": 11,
  "rounds_24h": 0,
  "profit_per_grid": 0.53,
  "est_liquidation_price": null,
  "open_orders_count": 15,
  "filled_orders_count": 22,
  "started_at": "2026-03-01T19:08:42+00:00",
  "stopped_at": null,
  "created_at": "2026-03-01T19:08:00+00:00",
  "updated_at": "2026-03-02T09:20:05+00:00"
}
```

---

## Error Responses

| HTTP | Meaning                         |
|------|---------------------------------|
| 401  | Missing or invalid API key      |
| 404  | Bot/resource not found          |
| 409  | Conflict (e.g. bot already active) |
| 422  | Validation error                |
| 503  | `EXTERNAL_API_KEY` not configured |

```json
{
  "success": false,
  "message": "Unauthorized. Provide a valid API key via X-API-Key header or Authorization: Bearer <key>."
}
```

---

## Example: MCP / Agent curl commands

```bash
KEY="your-api-key-here"
HOST="http://192.168.1.5:8100"

# Full system status
curl -H "X-API-Key: $KEY" "$HOST/api/v1/status"

# List all bots
curl -H "X-API-Key: $KEY" "$HOST/api/v1/bots"

# Detail of bot 1 (config + all orders + PNL history)
curl -H "X-API-Key: $KEY" "$HOST/api/v1/bots/1"

# Order stats for bot 1
curl -H "X-API-Key: $KEY" "$HOST/api/v1/orders/bot/1/stats"

# Open orders for bot 1
curl -H "X-API-Key: $KEY" "$HOST/api/v1/orders/bot/1?status=open"

# All filled orders (global)
curl -H "X-API-Key: $KEY" "$HOST/api/v1/orders?status=filled"

# Stop bot 1
curl -X POST -H "X-API-Key: $KEY" "$HOST/api/v1/bots/1/stop"

# Start bot 1
curl -X POST -H "X-API-Key: $KEY" "$HOST/api/v1/bots/1/start"

# Update bot 1 range (must be stopped)
curl -X PATCH \
  -H "X-API-Key: $KEY" \
  -H "Content-Type: application/json" \
  -d '{"price_lower": 65000, "price_upper": 78000, "grid_count": 20}' \
  "$HOST/api/v1/bots/1"
```

---

## Environment Variables (Dokploy)

Add to your Dokploy environment panel:

```
EXTERNAL_API_KEY=<generate with: openssl rand -hex 32>
```
