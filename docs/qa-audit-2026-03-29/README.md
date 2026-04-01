# Auditoría Completa — TradingBot (29 Mar 2026)

Primera revisión completa de todas las vistas y flujos de la aplicación.  
**Estado global: ❌ NOT READY FOR PRODUCTION**

---

## Agentes que participaron

| Archivo | Agente | Scope | Veredicto |
|---------|--------|-------|-----------|
| [tessa-auth-dashboard.md](./tessa-auth-dashboard.md) | Tessa (QA Specialist) | Auth flows + Dashboard | ❌ NEEDS WORK |
| [eva-bot-management.md](./eva-bot-management.md) | Eva (Evidence Collector) | Bot Management CRUD + Start/Stop | ❌ NEEDS WORK |
| [alex-orders-accessibility.md](./alex-orders-accessibility.md) | Alex (Accessibility Auditor) | Orders (3 páginas) + WCAG 2.2 | ❌ DOES NOT CONFORM |
| [maya-ai-agent-ux.md](./maya-ai-agent-ux.md) | Maya (UX Researcher) | AI Agent + Navegación global | ⚠️ C+ |
| [rex-binance-profile.md](./rex-binance-profile.md) | Rex (Reality Checker) | Binance Accounts + Profile + Telegram | ❌ NEEDS WORK |
| [vera-visual-design.md](./vera-visual-design.md) | Vera (UI Designer) | Auditoría visual completa de todas las vistas | ⚠️ B |

---

## Issues críticos (bloquean producción)

| ID | Issue | Encontrado por | Fix |
|----|-------|---------------|-----|
| BUG-01 | PNL positivo mostrado en **rojo** en `/orders/positions` | Vera + Eva | Revisar tokens de color en header de `Positions.tsx` |
| BUG-02 | Pantalla en negro intermitente en `/bots` (posible fallo de hidratación) | Eva | Error boundary + investigar race condition |
| BUG-03 | Modal "Detener bot" no cierra al hacer clic en Cancelar | Eva | Revisar handler `onCancel` del `AlertDialog` |
| BUG-04 | Recharts lanza `width(-1) height(-1)` en Dashboard | Tessa | Envolver en `ResponsiveContainer` con `minHeight` |
| BUG-05 | String "Ahora ago" visible en KPI de Dashboard | Tessa | Fix en `timeSinceCompact` — no concatenar sufijo inglés |
| ACC-01 | Orders inaccesibles en mobile (sidebar `hidden lg:hidden`) | Alex | Incluir sub-links de Orders en Sheet mobile |
| ACC-02 | Links sin nombre accesible en Positions (solo icono) | Alex | Agregar `aria-label` al link con ícono `ArrowUpRight` |

---

## Issues de alto impacto

| ID | Issue | Afecta |
|----|-------|--------|
| I18N-01 | Nav global en inglés ("Dashboard", "Trading", "AI Agent") | Todas las vistas |
| I18N-02 | Mensajes de validación Laravel en inglés | Formularios de Binance y Profile |
| I18N-03 | Fechas relativas en inglés (`2 weeks ago`) | Binance Accounts + posiblemente más |
| I18N-04 | Inglés residual en UI (Transfer, Futures, Preview Bot, Tool Calls, etc.) | Trading Terminal, AI Agent |
| UX-01 | Layout roto en `/orders/positions` (texto ilegible, área vacía) | Traders revisando posiciones |
| UX-02 | URL `/trading` → 404 sin layout ni enlace de retorno | Usuarios que infieren URL |

---

## Accesibilidad (Alex — 14 issues)

| Severidad | Cantidad |
|-----------|---------|
| 🔴 Críticos | 2 |
| 🟠 Serios | 3 |
| 🟡 Moderados | 6 |
| 🟢 Menores | 3 |

**Conformance WCAG 2.2 AA: DOES NOT CONFORM**

---

## Plan de acción

### Sprint 1 — Bloqueos (bugs + críticos)
- [ ] BUG-01: PNL en rojo en Posiciones
- [ ] BUG-03: Modal Cancelar no cierra
- [ ] BUG-04/05: Recharts + "Ahora ago"
- [ ] ACC-01: Orders inaccesibles en mobile
- [ ] ACC-02: Links sin nombre en Positions
- [ ] I18N-02: `lang/es/validation.php` + `APP_LOCALE=es`
- [ ] I18N-03: `Carbon::setLocale('es')` en AppServiceProvider
- [ ] UX-01: Fix layout de Posiciones

### Sprint 2 — Alto impacto
- [ ] I18N-01: Nav global en español
- [ ] I18N-04: Strings en inglés restantes (Transfer, Futures, Tool Calls, etc.)
- [ ] ACC: `lang="es"` en HTML, skip link, `h1`, table `scope`
- [ ] UX-02: Redirect `/trading` → `/bots` + página 404 con layout
- [ ] Fix semántica color nav activo + jerarquía de headings
- [ ] Reemplazar `alert()`/`confirm()` de Telegram por diálogos ShadCN
- [ ] Fix estilo de "Conexión exitosa" en Binance (color de éxito, no error)

### Sprint 3 — Pulido
- [ ] Empty states diseñados (Cuentas, Posiciones vacías, chart)
- [ ] Skeleton para TradingView/chart durante carga
- [ ] BUG-02: Investigar y resolver pantalla negra en `/bots`
- [ ] Dropdown usuario con fondo sólido
- [ ] Tilde "Órdenes" en título de historial
- [ ] `prefers-reduced-motion` en `animate-ping`
- [ ] Logout con 1 clic (sin necesidad de teclado)
- [ ] Auto-ocultar API Key tras X segundos de mostrada

---

## Qué está bien (no tocar)

- Split layout del login con glow verde — diseño fintech profesional ✅
- Estados de carga en botones (Ingresando…, Guardando…, Consultando…) ✅
- Modal de confirmación para eliminar cuenta (con contraseña) ✅
- Modal de Stop bot con descripción clara del comportamiento ✅
- Badges con texto (Compra/Venta, no solo color) en historial ✅
- Paginación con buttons semánticos ("Anterior"/"Siguiente") ✅
- API keys de Binance en `$hidden` + solo `masked_api_key` en respuestas ✅
- Paleta dark trading coherente con semántica financiera ✅
- ShadCN components aplicados consistentemente ✅
- Landmarks semánticos `<header>`, `<main>`, `<nav>` ✅
