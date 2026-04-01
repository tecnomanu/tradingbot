# UX Research Report — Maya: AI Agent + Navigation

**Fecha:** 29 Mar 2026  
**Agente:** Maya (UX Researcher)  
**Alcance:** `/ai-agent`, `/ai-agent/conversations/{id}`, arquitectura de navegación global  
**UX Score:** C+ (borde inferior de B- si se corrigen Posiciones + i18n)

---

## Arquitectura de Navegación

### Mapa del nav (layout autenticado)

```
Logo "GridBot" → /dashboard
├── Dashboard        → /dashboard         ⚠️ inglés
├── Trading          → /bots              ⚠️ inglés (label), URL real no es /trading
├── Actividad        → /orders/positions
│   ├── Posiciones   → /orders/positions
│   ├── Órdenes      → /orders/history
│   └── Grid Bots    → /orders/bots
├── AI Agent         → /ai-agent          ⚠️ inglés
└── Cuentas          → /binance-accounts

[Derecha]: tema toggle, menú usuario (Perfil, Cuentas Binance, Cerrar sesión)
```

### Evaluación del orden para el flujo del trader

El orden es **razonable** para el workflow esperado: resumen → operar → actividad → supervisor IA → cuentas. Lo que falta:
- Migas de pan en flujos profundos
- Sub-sección de Orders con jerarquía visual más clara entre sección principal y subsección

### Problemas de nomenclatura

| Label actual | Problema | Sugerencia |
|-------------|----------|-----------|
| "Dashboard" | En inglés | "Inicio" / "Panel" |
| "Trading" | En inglés + URL real es `/bots` | "Operaciones" / "Terminal" |
| "AI Agent" | En inglés | "Agente IA" |
| "GridBot" (marca) | Difiere de "TradingBot" en documentación | Unificar naming |

### Estado activo en nav

La pestaña actual se distingue correctamente (fondo/acento primario). En `/ai-agent/conversations/{id}` la sección "AI Agent" sigue activa — contexto correcto.

### Mobile

`AuthenticatedLayout` tiene Sheet con menú hamburguesa (visible `md:hidden`) y nav desktop (`hidden md:flex`). El sr-only del menú dice **"Menu"** en inglés. Sub-links de Actividad no están en el menú mobile (ver Issue crítico en reporte de Alex).

---

## AI Agent (`/ai-agent`)

### Primera impresión

No es un chat libre tipo asistente conversacional. Es un **panel de supervisor** con:
- Métricas globales (consultas, tool calls, acciones, duración promedio)
- Filtro "Todos los bots" / selector por bot
- CTA "Consultar Agente" (dispara consulta, no abre chat)
- Tres pestañas: Análisis rápido | Acciones del Bot | Conversaciones

El subtítulo *"Supervisor inteligente con herramientas de trading"* orienta bien.  
El título **"AI Trading Agent"** mezcla inglés.

### Problema de expectativa vs. realidad

**Nombre "AI Agent" + pestaña "Conversaciones" → el usuario espera un chat.**  
En la práctica es un **log de ejecuciones del supervisor** + consulta on-demand.

Esto genera:
1. Confusión en el primer uso ("¿dónde escribo el mensaje?")
2. Frustración al no encontrar un hilo conversacional
3. Las "Conversaciones" son historial de corridas, no intercambios bidireccionales

**Recomendación:** Renombrar a "Historial de consultas" o "Ejecuciones del agente" para alinear expectativa con realidad. Si el objetivo futuro es habilitar chat, preparar el terreno con nombre diferenciado.

### Flujo de consulta

- Selector de bot: en cabecera + en el modal de consulta ("Elegí un bot…"). Flujo claro.
- No hay campo de mensaje del usuario en la UI principal — la interacción es **disparar una consulta**, no escribir prompts personalizados.
- **Carga:** El botón principal pasa a "Consultando…" y queda disabled. **Buen feedback.**
- **Inconsistencia:** El modal puede seguir mostrando "Consultar" mientras fuera ya dice "Consultando…".

### Historial / conversaciones previas

Accesibles como lista y con enlace a detalle `/ai-agent/conversations/{id}`. Funciona pero la densidad del texto es muy alta.

### Errores del agente — UX fallida

Entradas con **"Sin análisis"** y mensaje en inglés **"Agent did not complete analysis"** en el detalle.  
En pestaña Conversaciones a veces aparece **"El agente no completó el análisis"** en español — doble fuente de verdad.

**Impacto para el trader:** El error es opaco. No dice por qué falló ni qué hacer. **No hay CTA de reintentar.**

### Detalle de conversación (`/ai-agent/conversations/1`)

- El mismo análisis se repite: bloque verde, JSON/`done`, "Análisis final" — **sobrecarga y fatiga visual**.
- "Acciones del Bot": mensajes técnicos crudos (ej: cooldown con muchos decimales de minutos).
- Badges en inglés: "completed", "scheduled", "Tool Calls", "tokens".

---

## UX Issues globales

### Fallas críticas

| Issue | Impacto |
|-------|---------|
| `/orders/positions` layout roto (texto superpuesto, área vacía) | El trader no puede validar PNL, rango, liquidación — **pérdida de confianza en los datos** |
| Mezcla español/inglés en nav, errores, modal, detalle de consulta | Disonancia cognitiva; incumplimiento del requisito de producto |
| URL `/trading` → 404 sin layout ni enlace de retorno | Callejón sin salida para usuarios que infieren la URL |

### Puntos de fricción

| Donde | Fricción |
|-------|---------|
| `/bots` | "Cargando…" prolongado en chart/libro/datos 24h |
| `/bots` | Botón "Transfer" en inglés |
| `/ai-agent` detalle | Mismo análisis repetido múltiples veces |
| "Acciones del Bot" | Mensajes técnicos con muchos decimales (ej: "cooldown: 14.333 minutos") |
| Login | Copy marketing lateral: "Charts, order book…" |
| Consulta manual | Feedback desincronizado entre modal y botón global durante carga |

### Problemas de arquitectura de información

- **Tres "puertas" al AI:** Dashboard (tarjeta + link "Panel AI"), sección AI Agent en detalle de bot, `/ai-agent` global. Sin diferenciación clara de propósito.
- **Actividad vs. AI Agent:** Ambas secciones muestran historial de eventos en parte — el trader puede no saber dónde mirar primero.
- **Sin breadcrumbs** en flujos profundos (ej: `/ai-agent/conversations/1` → solo flecha del browser para volver).

### Patrones inconsistentes

| Elemento | Inconsistencia |
|----------|---------------|
| Badges de estado | "completed"/"scheduled" en inglés vs. resto en español |
| CTAs verdes | Misma apariencia para acciones distintas (Consultar Agente vs. otras primarias) |
| Página 404 | Sin layout del producto (branding, nav, enlace a inicio) |
| `alert()`/`confirm()` en Telegram | Nativos del browser vs. modales ShadCN del resto de la app |

---

## Análisis de user journeys

| Objetivo del trader | Flujo esperado | Dónde se rompe |
|--------------------|----------------|----------------|
| Ver si el bot está funcionando bien | Dashboard → AI Agent → Análisis rápido | Mucho ruido "Sin cambios"; errores en inglés poco accionables |
| Entender qué hizo el agente | AI Agent → Acciones / Conversaciones / detalle | Detalle técnico útil pero repetitivo; términos en inglés; no hay reintentar |
| Revisar posiciones y riesgo | Actividad → Posiciones | **Layout roto** — texto ilegible, no se pueden verificar datos |
| Crear un bot | Trading | Carga prolongada de mercado/chart; "Transfer" en inglés; botón Crear disabled sin explicación |
| Sesión larga (consulta pesada) | AI Agent → espera → resultado | Sesión expiró antes de mostrar resultado → confusión sobre si "se perdió" el análisis |

---

## Recomendaciones

### Alta prioridad (impacto inmediato)

1. **Fix layout Posiciones** — grid, tipografía, spacing; usar el ancho disponible en desktop.
2. **Auditoría i18n completa** — nav, errores, badges, modal (`Close` → "Cerrar"), "Tool Calls" → "Llamadas a herramientas", etc.
3. **Página 404 con marca** — layout, enlace a Dashboard o `/bots`. Agregar `Route::redirect('/trading', '/bots')`.
4. **Estados de fallo del agente** — mensaje claro con categoría del error + botón "Reintentar" cuando aplique.
5. **Alinear loading** — modal y botón global en el mismo estado ("Consultando…" / spinner / no cerrable si aplica).

### Prioridad media

1. Renombrar "Conversaciones" → "Historial de consultas" o "Ejecuciones" si no hay chat real.
2. Filtros en Análisis rápido: por resultado (solo acciones, solo errores) y por fecha.
3. Reducir duplicación en detalle de consulta (un bloque resumen + sección técnica colapsable).
4. Unificar naming "GridBot" vs "TradingBot" en producto y documentación.

### Largo plazo

1. Si el objetivo es asistente conversacional, habilitar **hilo de chat** explícito separado del log del supervisor.
2. **Breadcrumbs** en Actividad y AI Agent.
3. Testing de **sesión larga** — notificar al usuario si la sesión expiró durante una consulta activa antes de perder el resultado.
4. Revisión responsive real del header en dispositivo físico.

---

## Score UX: C+ (bordes de B-)

La arquitectura general es comprensible y el módulo AI Agent es potente como herramienta de **transparencia operativa**, pero hoy un trader puede **perder confianza** por:
1. Posiciones ilegibles
2. Inglés residual y errores opacos del agente
3. 404 sin contexto y pantallas mínimas fuera del shell

Con esas tres líneas corregidas, el producto sube rápidamente a **B**.
