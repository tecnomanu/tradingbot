# QA Report — Tessa: Auth Flows + Dashboard

**Fecha:** 29 Mar 2026  
**Agente:** Tessa (QA Specialist)  
**Entorno probado:** `http://wizardgpt.tail100e88.ts.net:8100`  
**Credenciales:** `admin@tradingbot.local` / `Admin1234!`  
**Veredicto:** ❌ NEEDS WORK

---

## Auth Flow

### Login Page

| Check | Estado | Notas |
|-------|--------|-------|
| Diseño visual | ✅ | Dark mode, card centrada en mobile / split branding + form en `lg+`, acentos verdes |
| Errores de validación en español | ⚠️ | Con campos vacíos el browser puede mostrar validación HTML5 nativa (idioma dependiente del browser). Errores del servidor sí llegan en español si el locale está configurado. |
| Error por credenciales incorrectas en español | ❌ | No verificado: en la prueba con credenciales inválidas el flujo terminó en `/dashboard` (sesión activa previa). El mensaje es `trans('auth.failed')` y `config/app.php` tiene `APP_LOCALE=en` — riesgo real de inglés. |
| Login correcto redirige al dashboard | ✅ | Confirmado: tras "Ingresar" con credenciales válidas llega a `/dashboard`. |
| Estado de carga en botón submit | ✅ | Botón pasa a "Ingresando…", `disabled`, con spinner `Loader2`. |

**Fallos de idioma en login:**
- Panel izquierdo (desktop): `"Charts, order book y precios en vivo"` — mezcla inglés.
- Marca `"GridBot Trading"` — la palabra "Trading" en inglés.
- Título del documento: `"GridBot Trading"`.

---

### Password Recovery

| Check | Estado | Notas |
|-------|--------|-------|
| Enlace disponible | ✅ | "¿Olvidaste tu contraseña?" → `/forgot-password` |
| Formulario en español | ✅ | Título "Recuperar contraseña", instrucciones en español, campo email, botón "Enviar link" |
| Estado de carga | ✅ | "Enviando…" en código |
| Título de pestaña | ⚠️ | Documento muestra "GridBot Trading" en lugar de reflejar la página actual |

---

### Logout

| Check | Estado | Notas |
|-------|--------|-------|
| Visible y en español | ✅ | Menú usuario → "Cerrar sesión" |
| Un clic completa la acción | ❌ | Un clic solo enfoca el ítem (`Inertia Link method="post"`); requirió Barra espaciadora para completar. UX/accesibilidad riesgo alto. |

---

### Protected Routes

| Check | Estado |
|-------|--------|
| `/dashboard` sin sesión redirige a login | ✅ |

---

## Dashboard

### Layout & Navegación

- Jerarquía visual correcta: fila de 6 KPIs → gráfico PNL + últimas ejecuciones → tarjetas de bots/AI/cuentas.
- En viewport de prueba el menú hamburguesa apareció en lugar de barra horizontal desktop — **no se pudo certificar** el layout 1280px+ en este entorno (limitación del navegador embebido).
- **Navegación lateral móvil:** enlaces "Dashboard", "Trading", "AI Agent" en **inglés**. Botón de cierre del sheet: **"Close"** en inglés.

### Widgets / KPIs

- Bots activos, inversión, PNL total, ganancia grid, órdenes, AI agent — datos visibles.
- Gráfico PNL renderiza series correctamente; en algún reload hubo percepción de gráfico vacío (posible race condition).
- "Últimas ejecuciones", "Bots activos", log AI, resumen cuentas — presentes con datos.

### Texto — Fallos de español (criterio estricto)

| Elemento | Texto encontrado | Debería ser |
|----------|-----------------|-------------|
| Navegación | "Dashboard", "Trading", "AI Agent" | "Inicio", "Operaciones", "Agente IA" |
| KPI AI | sufijo ` ago` (ej: `"13m ago"`) | `"hace 13m"` |
| Bug combinado | `"Ahora ago"` cuando timestamp < 1 min | `"Hace un momento"` |
| KPI órdenes | `"exec"` en subtítulo | `"ej."` o "ejecuciones" |
| Gráfico | "Grid", "PNL Total" leyendas | Aceptable como términos de trading |

Código causante del `"Ahora ago"`:
```tsx
// resources/js/Pages/Dashboard/Index.tsx ~line 191
sub={`consultas · ${extended.ai_actions} acciones · ${timeSinceCompact(extended.last_ai_consult)} ago`}
```
`timeSinceCompact` devuelve `"Ahora"` cuando < 1 min → resultado: **"Ahora ago"**.

### Errores de consola

| Error | Origen | Severidad |
|-------|--------|-----------|
| `width(-1) height(-1) of chart should be greater than 0` | Recharts `AreaChart` | 🔴 Crítico — se dispara en cada render |
| `Failed to fetch dynamically imported module: .../Index-4OXmrgDy.js` | Chunk lazy-load | 🟡 Medio |
| `DialogContent` sin `DialogTitle` / sin `aria-describedby` | Radix UI | 🟢 Menor (accessibility warning) |

### Responsive (1280px)

- No certificado en el entorno de prueba (ventana siguió en patrón mobile).
- **Pendiente:** validar layout horizontal desktop en Chrome real a 1280px.

---

## Issues Found

| Severidad | Issue | Fix sugerido |
|-----------|-------|-------------|
| 🔴 Crítico | Texto visible NO 100% español: nav, "Trend", "exec", sufijo `ago`, "Close", branding, copy login | Traducir `NAV_ITEMS`, `timeSinceCompact`, strings de KPIs |
| 🔴 Crítico | Errores de consola Recharts (width/height -1) | Envolver `AreaChart` en `ResponsiveContainer` con alto mínimo |
| 🟡 Medio | Logout no completa con 1 clic (requiere teclado) | Reemplazar `Link method="post"` por un `<form>` con `<button>` submit |
| 🟡 Medio | Mensaje de login fallido probablemente en inglés | Crear `lang/es/auth.php`, configurar `APP_LOCALE=es` |
| 🟡 Medio | Intentos de login inválido terminaron en dashboard (sesión multi-pestaña) | Reproducir en sesión limpia; verificar middleware `guest` |
| 🟢 Menor | Título `<Head>` "Dashboard" en inglés | `<Head title="Panel principal">` o traducir |
| 🟢 Menor | Árbol accesible: "Trading automatizadode Grid Bots" (espacio faltante) | Revisar copy de la página de login |

---

## Veredicto: ❌ NEEDS WORK

**Criterios automáticos fallidos:**
- Texto no 100% español en UI visible
- Errores de consola en dashboard (Recharts)
- Logout UX/accesibilidad

**Lo que funciona bien:**
- Flujo de login válido
- Protección de rutas
- Estados de carga en formularios
- Password recovery disponible y en español
