# Bot Trading — Agent Handoff Guide

> Este documento es para el agente externo que monitorea y opera el bot a diario.
> Contiene credenciales, endpoints, flujos de análisis y ejemplos listos para usar.

---

## 1. Acceso al Panel Web

| | |
|---|---|
| **URL local** | `http://127.0.0.1:8080` |
| **URL Dokploy** | `http://192.168.1.5:8100` |
| **Email** | `admin@test.com` |
| **Password** | *(solicitar al owner — no almacenar en texto plano)* |
| **Horizon (queues)** | `http://127.0.0.1:8080/horizon` |

> Para rotar la API key: Panel → Profile → pestaña "API Key" → botón Rotate.

---

## 2. Autenticación API

Todas las rutas bajo `/api/v1/` requieren una de estas dos formas:

```
X-API-Key: <api_key>
```
o
```
Authorization: Bearer <api_key>
```

**API Key activa (user: admin@test.com):**
```
HJZjQdjLhylpsnFhaezyEJTn56TJxR5t3U6WZFmPFGYH2G1YLgmMl67I2uunJLuF
```

> ⚠️ Esta key es personal. Cada usuario tiene la suya. Si se compromete, rotarla desde el perfil.

**Base URL:**
```
http://127.0.0.1:8080/api/v1
```

---

## 3. Bot Activo

| Campo | Valor |
|---|---|
| **ID** | `6` |
| **Nombre** | `BTC Tight Grid` |
| **Symbol** | `BTCUSDT` |
| **Exchange** | Binance Testnet Futures |
| **Rango inferior** | $67,350.35 |
| **Rango superior** | $74,439.86 |
| **Grids** | 15 |
| **Inversión** | $3,000 USDT |
| **Leverage** | 3x |
| **Stop Loss** | $65,932.44 |
| **Take Profit** | Sin TP (libre) |
| **Liquidación est.** | $47,547 (−32.9% de margen) |

---

## 4. Endpoints — Referencia Completa

### 4.1 Status general

```
GET /api/v1/status
```

Devuelve resumen del sistema: bots activos/pausados, órdenes recientes, estado de Redis/Horizon.

```bash
curl -s http://127.0.0.1:8080/api/v1/status \
  -H "X-API-Key: HJZjQdjLhylpsnFhaezyEJTn56TJxR5t3U6WZFmPFGYH2G1YLgmMl67I2uunJLuF"
```

---

### 4.2 Bots

#### Listar todos los bots
```
GET /api/v1/bots
```
```bash
curl -s http://127.0.0.1:8080/api/v1/bots \
  -H "X-API-Key: HJZjQdjLhylpsnFhaezyEJTn56TJxR5t3U6WZFmPFGYH2G1YLgmMl67I2uunJLuF"
```

#### Detalle de un bot
```
GET /api/v1/bots/{id}
```
```bash
curl -s http://127.0.0.1:8080/api/v1/bots/6 \
  -H "X-API-Key: HJZjQdjLhylpsnFhaezyEJTn56TJxR5t3U6WZFmPFGYH2G1YLgmMl67I2uunJLuF"
```

Respuesta incluye: configuración completa, PNL, órdenes abiertas/cerradas, estado.

#### Actualizar configuración (solo cuando `status=stopped`)
```
PATCH /api/v1/bots/{id}
```

Campos editables:

| Campo | Tipo | Descripción |
|---|---|---|
| `name` | string | Nombre del bot |
| `price_lower` | float | Precio inferior del grid |
| `price_upper` | float | Precio superior del grid |
| `grid_count` | int | Número de grids (2–100) |
| `investment` | float | Capital total en USDT |
| `leverage` | int | Apalancamiento (1–20) |
| `stop_loss_price` | float\|null | Precio de SL |
| `take_profit_price` | float\|null | Precio de TP (null = sin TP) |

```bash
curl -s -X PATCH http://127.0.0.1:8080/api/v1/bots/6 \
  -H "X-API-Key: HJZjQdjLhylpsnFhaezyEJTn56TJxR5t3U6WZFmPFGYH2G1YLgmMl67I2uunJLuF" \
  -H "Content-Type: application/json" \
  -d '{"price_lower": 67000, "price_upper": 75000, "stop_loss_price": 65500}'
```

> ❌ Si el bot está activo devuelve `409 Conflict`. Siempre detenerlo primero.

#### Iniciar bot
```
POST /api/v1/bots/{id}/start
```
```bash
curl -s -X POST http://127.0.0.1:8080/api/v1/bots/6/start \
  -H "X-API-Key: HJZjQdjLhylpsnFhaezyEJTn56TJxR5t3U6WZFmPFGYH2G1YLgmMl67I2uunJLuF"
```

#### Detener bot
```
POST /api/v1/bots/{id}/stop
```
```bash
curl -s -X POST http://127.0.0.1:8080/api/v1/bots/6/stop \
  -H "X-API-Key: HJZjQdjLhylpsnFhaezyEJTn56TJxR5t3U6WZFmPFGYH2G1YLgmMl67I2uunJLuF"
```

---

### 4.3 Análisis de mercado

#### Precio actual de cualquier símbolo
```
GET /api/v1/market/price?symbol=BTCUSDT&testnet=true
```

| Param | Default | Descripción |
|---|---|---|
| `symbol` | — | Par (ej. BTCUSDT) |
| `testnet` | `false` | `true` para Binance Testnet Futures |

```bash
curl -s "http://127.0.0.1:8080/api/v1/market/price?symbol=BTCUSDT&testnet=true" \
  -H "X-API-Key: HJZjQdjLhylpsnFhaezyEJTn56TJxR5t3U6WZFmPFGYH2G1YLgmMl67I2uunJLuF"
```

#### Análisis completo para un bot ⭐
```
GET /api/v1/market/bot/{id}?interval=4h&candles=24
```

| Param | Default | Descripción |
|---|---|---|
| `interval` | `1h` | Intervalo de velas: `15m`, `1h`, `4h`, `1d` |
| `candles` | `24` | Cantidad de velas para cálculo RSI/trend |

```bash
curl -s "http://127.0.0.1:8080/api/v1/market/bot/6?interval=4h&candles=24" \
  -H "X-API-Key: HJZjQdjLhylpsnFhaezyEJTn56TJxR5t3U6WZFmPFGYH2G1YLgmMl67I2uunJLuF"
```

**Respuesta incluye:**
- `market`: precio actual, high/low 24h, volumen, % cambio
- `technicals`: RSI(14), señal RSI, tendencia, % cambio de tendencia
- `grid_positioning`: si el precio está dentro del rango, posición % en el grid, nivel actual (1-14), distancias al upper/lower
- `risk`: nivel (ok/warning/critical), alertas activas, distancia al SL/TP/liquidación
- `performance`: PNL total, rondas completadas, órdenes, horas activo

---

### 4.4 Órdenes

#### Todas las órdenes (paginado)
```
GET /api/v1/orders?page=1&per_page=50&status=filled
```

| Param | Default | Descripción |
|---|---|---|
| `page` | `1` | Página |
| `per_page` | `50` | Resultados por página (máx 200) |
| `status` | — | Filtrar: `filled`, `open`, `cancelled` |

#### Órdenes de un bot específico
```
GET /api/v1/orders/bot/{id}?status=filled&limit=20
```

#### Estadísticas de órdenes de un bot
```
GET /api/v1/orders/bot/{id}/stats
```

Devuelve: total filled/open/cancelled, volumen total, PNL por grid, tasa de éxito.

```bash
curl -s http://127.0.0.1:8080/api/v1/orders/bot/6/stats \
  -H "X-API-Key: HJZjQdjLhylpsnFhaezyEJTn56TJxR5t3U6WZFmPFGYH2G1YLgmMl67I2uunJLuF"
```

---

## 5. Flujo de Análisis Diario Recomendado

```
1. STATUS GENERAL
   GET /api/v1/status
   → Confirmar que el bot está activo y Horizon procesando

2. ANÁLISIS DE MERCADO
   GET /api/v1/market/bot/6?interval=4h&candles=24
   → Revisar:
     - in_range: debe ser TRUE
     - risk.level: debe ser "ok"
     - risk.alerts: debe estar vacío
     - rsi_14 + rsi_signal: señal de sobre-compra/venta

3. PERFORMANCE DEL BOT
   GET /api/v1/bots/6
   → Revisar open_orders_count, total_pnl, status

4. ÓRDENES RECIENTES
   GET /api/v1/orders/bot/6?status=filled&limit=10
   → Confirmar ejecución activa de grids

5. DECISIÓN DE RECONFIGURACIÓN (si aplica):
   a. Si precio sale del rango (in_range=false):
      → Detener, recalcular rango ±5% del precio actual, actualizar, reiniciar
   b. Si RSI > 75 (sobrecompra extrema en 4H):
      → Considerar achicar el upper o agregar TP temporal
   c. Si risk.level = "critical":
      → Detener inmediatamente, revisar SL/liquidación
```

---

## 6. Recálculo de Rango (fórmula estándar)

Cuando el precio sale del grid o está muy sesgado (>70% en un extremo):

```
price_lower  = precio_actual × 0.95   (−5%)
price_upper  = precio_actual × 1.05   (+5%)
stop_loss    = precio_actual × 0.93   (−7%, fuera del grid + buffer)
grid_spacing = (upper − lower) / (grid_count − 1)
```

**Secuencia de comandos:**
```bash
# 1. Detener
POST /api/v1/bots/6/stop

# 2. Actualizar
PATCH /api/v1/bots/6  { price_lower, price_upper, stop_loss_price }

# 3. Reiniciar
POST /api/v1/bots/6/start
```

---

## 7. Errores Comunes

| HTTP | Mensaje | Causa | Solución |
|---|---|---|---|
| `401` | Unauthorized | API key inválida o ausente | Verificar header `X-API-Key` |
| `403` | Forbidden | Bot no pertenece al usuario | Usar el bot ID correcto |
| `404` | Not found | Bot/recurso inexistente | Verificar ID |
| `409` | Conflict | Intentar PATCH con bot activo | Detener el bot primero |
| `422` | Validation error | Campos inválidos | Ver `errors` en la respuesta |
| `500` | Server error | Error interno | Revisar logs / Horizon |

---

## 8. MCP / Integración con Agente

Si el agente usa herramientas tipo `curl` o cliente HTTP, puede incorporar este flujo:

```python
API_KEY = "HJZjQdjLhylpsnFhaezyEJTn56TJxR5t3U6WZFmPFGYH2G1YLgmMl67I2uunJLuF"
BASE    = "http://127.0.0.1:8080/api/v1"
HEADERS = {"X-API-Key": API_KEY}

# Análisis diario
r = requests.get(f"{BASE}/market/bot/6", headers=HEADERS, params={"interval":"4h","candles":24})
data = r.json()["data"]

in_range  = data["grid_positioning"]["in_range"]
risk_lvl  = data["risk"]["level"]
alerts    = data["risk"]["alerts"]
rsi       = data["technicals"]["rsi_14"]
```

---

*Generado el 2026-03-01 | Bot Trading — Internal Docs*
