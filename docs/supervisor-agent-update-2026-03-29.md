# Actualización del Agente Supervisor — 29 Mar 2026

## Contexto

Se analizó la especificación del "Agente Supervisor de Trading" y se implementó en el proyecto. El sistema ya tenía una base sólida (bucle de herramientas, acciones, logs). Esta actualización lo eleva a un modelo de supervisión estructurado, con clasificación de contexto, máquina de estados e inercia real.

---

## Qué se cambió y por qué

### 1. Nuevo modelo mental del agente (Prompt — Fase 1)

**Antes:** el agente reaccionaba a indicadores aislados (RSI > 78, posición% > 90, etc.) sin un marco de evaluación coherente.

**Ahora:** el agente razona en 3 capas ("anillos") antes de decidir:

| Anillo | Qué evalúa | Fuente de datos |
|--------|-----------|-----------------|
| 1 — Bot | ¿Está cómodo el bot? Posición en rango, actividad, origen del PNL, intervenciones recientes | `get_bot_status` |
| 2 — Mercado | ¿Qué estructura tiene el mercado? Lateral, tendencia, ruptura, volatilidad | `get_market_data` |
| 3 — Contexto externo | ¿Hay algo anómalo? | Calculado desde vol_ratio y variación 24h |

A partir de los 3 anillos determina **dos estados** y aplica la **matriz de decisión**:

```
estado_mercado + estado_bot → estado_agente
```

**Estados del bot:** Cómodo / Exigido / Desalineado / Inviable

**Estados del mercado:** Favorable / Vigilante / Frágil / Incompatible

**Estado del agente resultante:**
- **Favorable** → solo observar, prohibido tocar nada
- **Vigilancia** → log de warning, sin acciones
- **Protección** → solo ajustar SL si es insuficiente
- **Reconstrucción** → adjust_grid + cancelar órdenes + nuevo SL
- **Retiro** → stop bot + cerrar posición (solo manual)

Cada acción tiene **prohibiciones explícitas** por estado.

---

### 2. Tesis estructurada obligatoria (Prompt + Tool)

**Antes:** el agente cerraba con texto libre en español.

**Ahora:** cada consulta produce un JSON de tesis auditble:

```json
{
  "regime": "lateral_clean",
  "movement_quality": "clean",
  "bot_state": "comfortable",
  "market_state": "favorable",
  "agent_state": "favorable",
  "trajectory": "stable",
  "external_context": "neutral",
  "action_taken": "none",
  "reason": "Bot centrado al 58%, RSI 52, vol_ratio 1.1x — sin señal de intervención",
  "narrative": "El mercado opera en rango lateral limpio con baja volatilidad..."
}
```

Esto hace el sistema **auditable**: se puede revisar el razonamiento de cada ciclo.

---

### 3. Historial de consultas como memoria (Fase 2 — nuevo tool)

**Antes:** el agente veía solo el ciclo actual, sin contexto histórico.

**Ahora:** nuevo tool `get_previous_consultations` que devuelve las últimas N tesis. El agente puede ver si la situación lleva 3 ciclos deteriorándose o si mejoró desde la última vez.

> *"Piensa en película, no en foto."* — esto lo implementa en código.

---

### 4. Estado persistente del agente en la base de datos (Fase 2)

**Antes:** cada consulta era stateless, sin memoria de en qué estado estaba el agente.

**Ahora:** tres columnas nuevas en la tabla `bots`:
- `agent_state` — el estado confirmado del agente para ese bot
- `agent_state_streak` — cuántos ciclos consecutivos en ese estado
- `ai_next_consultation_at` — cuándo debe ser la próxima consulta

El `get_bot_status` ahora devuelve el `agent_state` previo al agente, que lo puede usar para medir continuidad.

---

### 5. Inercia real en PHP (Fase 3)

**Antes:** el agente podía cambiar de "favorable" a "retiro" en un solo ciclo.

**Ahora:** el código en PHP **bloquea** transiciones de estado que no tengan confirmación en dos observaciones consecutivas. Si el agente propone un cambio de estado, la primera vez se almacena como "pendiente" (en Cache) y solo se confirma la segunda vez que llega con la misma propuesta.

> Esto es enforcement real, no solo instrucción en el prompt.

---

### 6. Frecuencia dinámica de consultas (Fase 3)

**Antes:** el bot consultaba al agente cada N minutos fijo (configurable en settings).

**Ahora:** el agente devuelve `next_check_minutes` en cada ciclo y el scheduler lo respeta:
- Estado **Favorable** → propone 60-120 min (innecesario revisar frecuente)
- Estado **Vigilancia** → 30-60 min
- Estado **Protección** → 15-30 min
- Estado **Reconstrucción** → 10-15 min

Si el agente no propone tiempo, sigue usando el intervalo configurado manualmente.

---

### 7. Contexto externo auto-clasificado (Fase 3)

**Antes:** no había clasificación de contexto externo (no había feed de noticias).

**Ahora:** se clasifica automáticamente desde `vol_ratio` y `chg24h`:
- `neutral` — vol_ratio < 2x, variación normal
- `uncertainty_rising` — vol_ratio 2-3x o variación > 3%
- `relevant_event` — vol_ratio > 3x o movimiento impulsivo extremo

No requiere API de noticias externas; usa datos de Binance que ya se consultaban.

---

### 8. Información de origen de PNL en get_bot_status (Fase 2)

**Antes:** el agente veía `total_pnl` y `grid_profit` por separado.

**Ahora:** recibe también un campo `pnl_origin` clasificado:
- `from_grid` — el bot genera PNL por oscilación (correcto)
- `from_trend` — el PNL viene de movimiento direccional (señal de atención)
- `floating_loss` — pérdida no realizada (riesgo)
- `mixed` — combinación

---

## Estado en producción

| Item | Estado |
|------|--------|
| Deploy | ✅ Aplicado (Dokploy + auto-migración) |
| Tests | ✅ 165/165 pasando |
| Bots activos | ✅ 2 activos (Bot #1: +$94 USDT / Bot #2: +$22 USDT) |
| AI Agent | ✅ Funcionando (706 consultas históricas) |
| Nuevas columnas DB | ✅ Se aplican en el primer startup del contenedor |

---

## Qué revisar para responder a la especificación

### ✅ Implementado completamente

- Modelo de 3 anillos (pipeline de evaluación)
- Clasificación de estado del bot y mercado
- Matriz de decisión mercado + bot → agente
- Acciones permitidas/prohibidas por estado
- Tesis obligatoria con formato JSON
- Principio de inercia (con enforcement en código)
- Frecuencia dinámica de observación
- Contexto externo (inferido desde datos de mercado)
- Auditabilidad total (toda tesis queda en `ai_conversations`)
- Historial de ciclos previos disponible para el agente

### ⚠️ Implementado parcialmente / simplificado

| Ítem del documento | Implementación actual | Gap |
|----|----|----|
| Anillo 3 — Contexto externo | Calculado desde vol_ratio, sin feed de noticias | Sin acceso a noticias reales (CryptoPanic, CoinGecko); se podría agregar como Fase 4 |
| Frecuencia dinámica | El agente sugiere, el scheduler respeta | No hay UI para ver ni ajustar `ai_next_consultation_at` manualmente |
| Estado RETIRO | Disponible en modo manual | En modo scheduled el agente no puede llegar a RETIRO (por diseño: evita apagado permanente) |

### ❌ No implementado (fuera de scope)

- Feed de noticias externas en tiempo real (no había en la spec ningún proveedor específico)
- UI para visualizar el `agent_state` por bot (queda como columna interna, no se muestra en el dashboard)

---

## Próximos pasos sugeridos (Fase 4 opcional)

1. **UI para agent_state** — mostrar en la tarjeta del bot el estado actual del agente (Favorable / Vigilancia / etc.) con badge de color
2. **Feed de noticias** — integrar CryptoPanic API para el Anillo 3 real
3. **Notificaciones por estado** — alertar por Telegram cuando el agente cambia de estado (ej: Favorable → Protección)
4. **Test de integración del agente** — agregar un test que ejecute una consulta completa de extremo a extremo y valide la estructura del JSON de tesis
