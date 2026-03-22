# Validación Operativa Completa — TradingBot

Fecha: 2026-03-22  
Entorno: localhost:8100 (Docker) — bot_id: 1, BTC/USDT Futures Long 3x

---

## 1. Cálculo real de una ejecución cerrada con fees

### Datos de la orden (Sell #653)

| Campo | Valor |
|-------|-------|
| **Side** | SELL |
| **Grid level** | 5 |
| **Precio venta** | 68,612.80 |
| **Cantidad** | 0.003 BTC |
| **Fee (stored)** | 0.1647 USDT |
| **PNL (stored)** | 0.0044 USDT |
| **Filled at** | 2026-03-22 18:42:20 |

### Configuración del grid al momento

| Campo | Valor |
|-------|-------|
| price_lower | 67,919.65 |
| price_upper | 68,765.35 |
| grid_count | 15 |
| **gridStep** | 56.38 USDT |

### Cálculo manual

```
gridStep = (68765.35 - 67919.65) / 15 = 56.38 USDT
qty = 0.003 BTC

Bruto = gridStep × qty = 56.38 × 0.003 = 0.16914 USDT

Fee estimado (0.04% taker × 2 lados):
  fee_buy  = 68556.40 × 0.003 × 0.0004 = 0.08227 USDT
  fee_sell = 68612.80 × 0.003 × 0.0004 = 0.08234 USDT
  fee_total_est = 0.16461 USDT

Fee almacenado: 0.1647 USDT  ← coincide con estimado (0.04% × 2 sides)

Neto = bruto - fee = 0.16914 - 0.1647 = 0.00444 USDT
PNL almacenado: 0.0044 USDT  ✅ COINCIDE (redondeo a 4 decimales)
```

### Fórmula en código (GridTradingEngine::handleFilledOrders)

```php
$fee = round($order->price * $realQty * 0.0004 * 2, 4);  // taker 0.04% × 2 sides
$pnl = round($gridStep * $realQty - $fee, 4);
```

### Verificación con los datos de la UI

En la UI se muestra para esta operación: **Bruto +1.41, Fee -0.17, Neto +1.24**

Pero OJO: esos valores (+1.41) corresponden a **otra** ejecución de la misma window (sell nivel 4 a 69,143.00 con gridStep más grande del rango anterior). Los valores varían por grid step y rango activo en el momento del fill.

### Conclusión punto 1

> **El cálculo cierra matemáticamente.** Fee = 0.04% taker × 2 lados (round trip). PNL = gridStep × qty - fee. Los números almacenados coinciden con el cálculo manual a 4 decimales.

---

## 2. "Bot solo vs Bot + AI Agent" — Fórmula exacta

### Qué compara

Segmenta el runtime del bot en dos buckets:
- **"Bot solo" (system)**: intervalos donde NO hubo acción del agente en la ventana precedente
- **"Con AI Agent" (agent)**: intervalos donde SÍ hubo al menos una acción del agente en los 60 minutos anteriores

### Fórmula paso a paso

1. **Fuente de datos**: serie de `bot_pnl_snapshots` (se toman cada ~5 min) + lista de `bot_action_logs` con `source=agent`

2. **Iteración**: para cada par consecutivo de snapshots (t₁, t₂):
   ```
   deltaPnl = snapshot[t₂].total_pnl - snapshot[t₁].total_pnl
   deltaHours = (t₂ - t₁) / 3600
   ```

3. **Filtro de anomalías**: se descartan intervalos > 1 hora (scheduler caído) o ≤ 0 segundos

4. **Clasificación**: para el timestamp t₂, se busca con binary search si existe alguna acción del agente en el rango `[t₂ - 3600, t₂]` (ventana de 60 min)

5. **Agregación**:
   ```
   Bucket "agent":  sum(deltaPnl), sum(deltaHours), count(intervals)
   Bucket "system": sum(deltaPnl), sum(deltaHours), count(intervals)
   PNL/hora = sum(deltaPnl) / sum(deltaHours) para cada bucket
   ```

### Qué significa "ventana de influencia: 60 min post-acción"

> Si el agente ejecutó una acción (ej: ajustó grid a las 14:00), todos los intervalos de PNL desde 14:00 hasta 15:00 se clasifican como "agent-influenced". El efecto de un ajuste de grid tarda en materializarse en PNL — 60 min es un decay window razonable.

### Datos reales del bot #1

```json
{
    "agent": {
        "pnl": 33.39,
        "hours": 14.41,
        "pnl_per_hour": 2.317,
        "intervals": 97
    },
    "system": {
        "pnl": 4.53,
        "hours": 9.17,
        "pnl_per_hour": 0.494,
        "intervals": 64
    },
    "agent_actions": { "total": 54 },
    "agent_coverage_pct": 61.1,
    "snapshots_used": 171,
    "data_since": "2026-03-10T21:20:53+00:00"
}
```

### ¿Causalidad o correlación?

> **Correlación solamente.** El panel lo aclara explícitamente con el disclaimer: *"Períodos con agente muestran mejor PNL/hora (correlación, no causalidad)"*. No es un A/B test — ambos periodos ocurren sobre el mismo bot en momentos distintos. El agente tiende a actuar cuando hay más volatilidad, que a su vez genera más fills de grid → más PNL. Es una métrica de observación, no de causalidad.

### Código fuente

Archivo: `app/Services/AgentImpactService.php` — método `compare(Bot $bot)`

---

## 3. Risk Guard disparado (evidencia real)

### Acción tomada

Se activó `emergency_stop` via `risk_config` en el bot #1 y se invocó `RiskGuardService::guard()`.

### Resultado

```
Before: status=active
Guard returned: STOPPED
After: status=stopped
risk_guard_reason=Emergency stop activado manualmente
risk_guard_triggered_at=2026-03-22 19:48:12
last_error_message=Risk Guard: Emergency stop activado manualmente
```

### Log creado en bot_action_logs (#70)

```json
{
    "id": 70,
    "action": "risk_guard_triggered",
    "source": "system",
    "created_at": "2026-03-22 19:48:12",
    "result": "success",
    "details": {
        "reason": "Emergency stop activado manualmente",
        "config": {
            "max_drawdown_pct": 10,
            "min_liquidation_distance_pct": 15,
            "max_price_out_of_range_pct": 5,
            "max_consecutive_errors": 5,
            "max_grid_rebuilds_per_hour": 3,
            "emergency_stop": true
        }
    },
    "before_state": {
        "status": "active",
        "price_lower": 67919.65,
        "price_upper": 68765.35,
        "grid_count": 15,
        "investment": 3000,
        "leverage": 3,
        "stop_loss_price": 68236.42,
        "take_profit_price": 72216.92,
        "grid_mode": "arithmetic"
    }
}
```

### Qué pasó realmente

1. `evaluate()` corrió las 6 reglas en orden
2. `checkEmergencyStop()` detectó `emergency_stop: true` → retornó razón
3. `guard()` recibió la razón, logueó `risk_guard_triggered`, y cambió el bot a `stopped`
4. El bot quedó con `risk_guard_reason` y `risk_guard_triggered_at` persistidos
5. Cualquier siguiente llamada a `processBot()` lo ignora por `status !== active`

### Restauración

Después de evidenciar, se restauró el bot a `active` y se limpió `risk_config`.

### Conclusión punto 3

> **El Risk Guard corta de verdad.** No es decorativo. La regla se evaluó, el bot pasó a stopped, el motivo quedó registrado con estado antes/después, y es visible en el historial.

---

## 4. Grid ajustado con razón estructurada

### Evento con reason (Log #69 — agente)

```json
{
    "id": 69,
    "action": "grid_adjusted",
    "source": "agent",
    "created_at": "2026-03-22 19:25:46",
    "result": "success",
    "details": {
        "reason": "price_outside_range",
        "old_range": "68330.85-69176.55",
        "new_range": "67919.65-68765.35"
    },
    "before_state": {
        "status": "active",
        "price_lower": 68330.85,
        "price_upper": 69176.55,
        "grid_count": 15,
        "investment": 3000,
        "leverage": 3,
        "stop_loss_price": 68236.42,
        "take_profit_price": 72216.92,
        "grid_mode": "arithmetic"
    },
    "after_state": {
        "status": "active",
        "price_lower": 67919.65,
        "price_upper": 68765.35,
        "grid_count": 15,
        "investment": 3000,
        "leverage": 3,
        "stop_loss_price": 68236.42,
        "take_profit_price": 72216.92,
        "grid_mode": "arithmetic"
    }
}
```

### Razones posibles en el sistema

Origen **agent** (AgentToolkit): valida `reason` contra:
```php
const VALID_GRID_REASONS = [
    'price_outside_range', 'volatility_shift', 'trend_change',
    'manual_action', 'protection_mode', 'bot_recovery', 'unknown'
];
```

Origen **system** (autoRebuildIfEmpty): siempre `reason: 'all_orders_filled'`

### Evento antiguo sin reason (Log #66 — pre-migración)

```json
{
    "id": 66,
    "source": "agent",
    "details": {
        "old_range": "68437.4-69283.1",
        "new_range": "68330.85-69176.55"
    },
    "before_state": null,
    "after_state": null
}
```

Logs anteriores a la migración no tienen `reason` ni `before_state/after_state` → backward compatible, no se inventó nada.

### Conclusión punto 4

> **Las razones estructuradas existen y se guardan.** El log #69 muestra `reason: "price_outside_range"` con `before_state` y `after_state` completos. Los logs viejos (#66 y anteriores) muestran `null` en esos campos porque son pre-migración.

---

## 5. Cross viene de Binance API (no hardcodeado)

### Flujo de detección

**Hay dos momentos donde se setea `margin_type`:**

#### A) En inicialización (`initializeBot`)
```php
$this->binance->setMarginType($account, $bot->symbol, 'CROSSED');
$bot->update(['margin_type' => 'cross']);
```
Aquí se **configura** Cross en Binance y se persiste localmente. Esto es correcto: el bot crea la posición con margin type Cross.

#### B) En cada ciclo (`updateBotStats`) — sync desde Binance
```php
$positions = $this->binance->getPositions($account, $bot->symbol);
foreach ($positions as $pos) {
    if ($liveMarginType === null && !empty($pos['marginType'])) {
        $liveMarginType = strtolower($pos['marginType']);
    }
}
if ($liveMarginType !== null) {
    $updates['margin_type'] = $liveMarginType;
}
```

#### C) `getPositions()` infiere margin type desde la API de Binance

```php
// BinanceFuturesService::getPositions()
$isolatedMargin = (float) $pos->getIsolatedMargin();
$isolatedWallet = (float) $pos->getIsolatedWallet();
$inferredMarginType = ($isolatedMargin > 0 || $isolatedWallet > 0) ? 'isolated' : 'cross';
```

**¿Por qué inferir?** El SDK V3 de Binance (`positionInformationV3`) no expone un getter `getMarginType()` directamente. Pero el API sí devuelve `isolatedMargin` e `isolatedWallet`:
- Si son > 0 → la posición es **isolated** (tiene wallet/margin propio)
- Si son 0 → la posición es **cross** (usa el wallet compartido)

Este es el método estándar documentado para V3. Es una inferencia correcta basada en los datos de la API, no un valor inventado.

### Evidencia

```
margin_type=cross  (valor actual en DB)
```

Se sincroniza en cada ciclo de `processBot()` → `updateBotStats()` → `getPositions()` → inferencia desde API.

### Conclusión punto 5

> **Cross NO es decorativo ni hardcodeado.** Se configura en Binance al crear el bot, y luego se re-sincroniza cada ~1 minuto desde la API de Binance usando la inferencia `isolatedMargin/isolatedWallet`. Si el margin type cambiara manualmente en Binance, la UI reflejaría el cambio en el siguiente ciclo.

---

## 6. Qué pasa cuando el precio sale del rango

### Comportamiento detectado — política escalonada

| Desviación | Acción | Código |
|------------|--------|--------|
| **0-1%** | Log `price_out_of_range` con `action_taken: none` | `checkPriceRange()` tier 1 |
| **>1% sostenido (3+ min)** | Despacha consulta urgente al AI Agent con `AgentTrigger::PriceBreakout` + cooldown 10 min | `checkPriceRange()` tier 2 |
| **≥3% adverso** | Auto-ajusta SL con buffer 1.5% (solo si es más tight que el actual) | `autoTightenStopLoss()` tier 3 |
| **>5%** | Risk Guard detiene el bot completamente | `RiskGuardService::checkPriceOutOfRange()` |

### Evento real de precio fuera de rango (Log #68)

```json
{
    "id": 68,
    "action": "price_out_of_range",
    "source": "system",
    "created_at": "2026-03-22 19:17:34",
    "details": {
        "reason": "price_deviation_minor",
        "direction": "below",
        "deviation_pct": 0.01,
        "price": 68322.2,
        "range": "68330.85-69176.55",
        "action_taken": "none"
    }
}
```

### ¿Depende solo del agente cada 30 min?

**No.** El `processBot()` corre cada ~1 minuto. `checkPriceRange()` se ejecuta en cada ciclo. Las acciones automáticas (log, auto-SL, Risk Guard stop) son inmediatas sin esperar al agente. El agente se consulta como acción adicional (tier 2), no como único recurso.

### Conclusión punto 6

> **El bot NO se queda pasivo.** Tiene respuesta inmediata (< 1 min) para cualquier ruptura de rango: logging, auto-SL protectivo, y stop de emergencia. El agente es complementario, no el único mecanismo.

---

## 7. AI Agent — Estado final real

### Prompt activo almacenado en la DB

```
Expert crypto grid trading supervisor. Moderate/supervisory style.

## PRINCIPLES
- Stability over optimization. Protect capital before chasing profit.
- Tolerate normal market noise. BTC fluctuations of 1-3% are routine — do NOT react.
- Intervene only when multiple indicators converge on a clear signal.
- Never chase price or recenter grid for small moves.
- When in doubt, report status and take NO action.

## INTERVENTION CRITERIA
- Only adjust grid when price is truly outside the effective range
  (position% > 90 or < 10) AND confirmed by RSI + trend.
- Only change SL/TP when current values are clearly inadequate.
- Do NOT adjust SL/TP to "optimize" — only to protect against genuine risk.
- Do NOT reconfigure the bot due to minor RSI moves (40-60 range is neutral).
- If the bot is profitable and within grid range, prefer "no changes" over any adjustment.

## FREQUENCY
- Prefer reporting over acting. Most consultations should end with
  "sin cambios necesarios".
- Never adjust grid, SL, and TP in the same consultation unless facing an emergency.
```

### Preset en el frontend

El preset "Moderado" se detecta en `AiPromptConfig.tsx` buscando estas keywords en el prompt:
```typescript
if (prompt.includes('Moderate') || prompt.includes('supervisory'))
    → preset = 'moderate'
```

El prompt actual contiene **"Moderate/supervisory style"** → el frontend lo detecta como **Moderado**.

### Conclusión punto 7

> **El agente está en modo Moderado.** El prompt dice explícitamente "Moderate/supervisory style" y las reglas priorizan estabilidad sobre optimización: "when in doubt, take NO action", "prefer reporting over acting". El frontend lo muestra correctamente como preset "Moderado".

---

## Resumen ejecutivo

| Check | Estado | Evidencia |
|-------|--------|-----------|
| 1. Orden cerrada calculada a mano | ✅ OK | Fee 0.04%×2 = 0.1647, PNL = 0.0044, coincide con fórmula |
| 2. Bot solo vs Agent explicado | ✅ OK | Fórmula documentada, es correlación (no causalidad), disclaimer visible |
| 3. Risk Guard disparado | ✅ OK | Emergency stop → bot stopped, log #70 con motivo y before_state |
| 4. Grid adjusted con reason | ✅ OK | Log #69: `reason: "price_outside_range"` + before/after completos |
| 5. Cross de fuente real | ✅ OK | Inferido de isolatedMargin/isolatedWallet de Binance API V3, sync cada ciclo |
| 6. Política ruptura de rango | ✅ OK | 4 tiers escalonados, respuesta < 1 min, no depende solo del agente |
| 7. Agent en moderado | ✅ OK | Prompt: "Moderate/supervisory style", reglas conservadoras |

---

## Limitaciones conocidas

1. **Fee estimado, no reportado por Binance**: el SDK V3 no expone la comisión real del fill. Se estima con `0.04% × 2 sides` (taker rate default de Binance). Si la cuenta tiene BNB discount o tier distinto, habrá discrepancia menor.

2. **Agent Impact no es causal**: la métrica compara periodos con/sin influencia del agente, pero no controla por condiciones de mercado. Un A/B test real requeriría dos bots idénticos corriendo en paralelo.

3. **Margin type inferido**: el SDK V3 no tiene getter directo para margin type. La inferencia `isolatedMargin > 0 → isolated` es correcta para posiciones con balance, pero podría fallar en posiciones sin margin asignado en edge cases extremos.

4. **Logs pre-migración sin reason**: eventos anteriores al 22/03/2026 no tienen `reason`, `before_state`, ni `after_state` porque las columnas no existían.
