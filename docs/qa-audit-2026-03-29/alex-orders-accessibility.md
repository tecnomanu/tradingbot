# Accessibility Audit Report — Alex: Orders Section

**Fecha:** 29 Mar 2026  
**Agente:** Alex (Accessibility Auditor — WCAG 2.2)  
**Páginas auditadas:** `/orders/bots`, `/orders/history`, `/orders/positions`  
**Standard:** WCAG 2.2 Level AA  
**Veredicto:** ❌ DOES NOT CONFORM

---

## Metodología

- **UI en vivo (staging):** Navegación a cada URL, revisión de layout y árbol de accesibilidad.
- **Teclado:** Muestreo de Tab desde `/orders/history`; verificación de controles de sort.
- **Revisión de código:** `resources/js/Pages/Orders/*`, `OrdersLayout.tsx`, `AuthenticatedLayout.tsx`, `app.blade.php`, `config/app.php`.

---

## Resumen

| Categoría | Cantidad |
|-----------|---------|
| **Total issues** | **14** |
| Critical (bloquea acceso) | 2 |
| Serious (barrera mayor) | 3 |
| Moderate (workaround existe) | 6 |
| Minor | 3 |

**Conformance WCAG 2.2 AA: DOES NOT CONFORM**

---

## Contenido por página

### `/orders/bots`
Lista vertical de **cards** por bot: símbolo, leverage/side badge, margen, PNL total (%), ganancia grid, trend PNL, rango de precios, cantidad de rejillas, rondas, conteo de órdenes, fecha "Activo desde", liquidación, acciones "Detener"/"Iniciar"/"Editar"/"Detalles".

Header con totales de inversión y PNL cuando hay bots activos. Empty state: "No hay bots" + link a crear.

### `/orders/history`
Título + filtros (Estado, Lado, Par, Bot) + Reset + **Exportar** (muestra toast "Próximamente"). Tabla con columnas: Par, Bot, Lado, Estado, Precio, Cantidad, PNL, Fecha. Lado/Estado usan **badges con texto** ("Compra"/"Venta"). PNL usa **color + signo** numérico. Paginación con "Anterior"/"Siguiente".

### `/orders/positions`
Cards por posición abierta: link al bot, badge de uptime, leverage + Largo/Corto/Neutral, inversión, PNL total, ganancia grid, trend PNL, rango, conteo de órdenes, rondas, liquidación. **Sin botón "Cerrar posición"** — solo navegación a `/bots/{id}`.

---

## Issues de Accesibilidad

### Issue 1 — Subsección Orders inaccesible en mobile
**WCAG:** 2.4.5 Multiple Ways (AA) | **Severidad: CRÍTICA**

`ORDER_NAV_ITEMS` (Grid Bots, Órdenes, Posiciones) está en sidebar `hidden lg:hidden` en `OrdersLayout.tsx`. El menú hamburguesa mobile de `AuthenticatedLayout.tsx` solo contiene `NAV_ITEMS`. Los usuarios mobile **no pueden acceder** a "Historial" ni "Grid Bots" sin editar la URL manualmente.

**Fix:** Incluir sub-links de Orders en el Sheet mobile, anidados bajo "Actividad", o usar un `<select>` visible en small screens para las 3 rutas.

---

### Issue 2 — Links sin nombre accesible en Positions
**WCAG:** 4.1.2 Name, Role, Value (A) + 2.4.4 Link Purpose (A) | **Severidad: CRÍTICA**

En `Positions.tsx`, hay un `Link` que envuelve solo `<ArrowUpRight />`. El árbol de accesibilidad muestra `role: link` con **nombre vacío**. Screen readers anuncian "link" sin contexto.

**Fix:**
```tsx
<Link href={`/bots/${bot.id}`} aria-label={`Ver detalles del bot ${bot.symbol}`}>
  <ArrowUpRight />
</Link>
```

---

### Issue 3 — Headers de tabla sortable no operables por teclado
**WCAG:** 2.1.1 Keyboard (A) | **Severidad: SERIA**

En `OrderHistory.tsx`, `SortHeader` renderiza `<th onClick={…}>`. Los `<th>` no son focusables por defecto; el sort es **click-only**.

**Fix:**
```tsx
<th>
  <button type="button" onClick={onSort} aria-sort={currentSort === col ? 'ascending' : 'none'}>
    {label}
  </button>
</th>
```

---

### Issue 4 — Selects de filtro sin label accesible
**WCAG:** 4.1.2 Name, Role, Value (A) + 1.3.1 Info and Relationships (A) | **Severidad: SERIA**

Los 4 `FilterSelect` en `OrderHistory.tsx` usan un `<span>` como label (no asociado con `htmlFor`). Screen readers anuncian los 4 como **"Todos"** (el valor seleccionado) sin distinguir cuál es Estado, Lado, Par o Bot.

**Fix:**
```tsx
<label htmlFor={`filter-${id}`} className="text-sm">{label}</label>
<select id={`filter-${id}`} ...>
```

---

### Issue 5 — Atributo `lang` del documento no coincide con la UI
**WCAG:** 3.1.1 Language of Page (A) | **Severidad: SERIA**

`app.blade.php` usa `lang="{{ str_replace('_', '-', app()->getLocale()) }}"`. El `.env` tiene `APP_LOCALE=en`, por lo que el HTML probablemente tiene `lang="en"` mientras todo el contenido está en español. Los lectores de pantalla pronuncian el texto en español con pronunciación inglesa.

**Fix:** Cambiar a `APP_LOCALE=es` en `.env` de staging y producción.

---

### Issue 6 — Sin skip link a contenido principal
**WCAG:** 2.4.1 Bypass Blocks (A) | **Severidad: MODERADA**

No existe ningún "Saltar al contenido" antes del header/nav en `AuthenticatedLayout.tsx`. Usuarios de teclado deben tabear por logo y navegación en cada carga de página.

**Fix:**
```tsx
<a href="#main-content" className="sr-only focus:not-sr-only focus:absolute ...">
  Saltar al contenido
</a>
// ...
<main id="main-content">
```

---

### Issue 7 — Sin `<h1>` en ninguna página
**WCAG:** 1.3.1 Info and Relationships (A) + 2.4.6 Headings and Labels (AA) | **Severidad: MODERADA**

Las tres páginas de Orders (y otras en la app) comienzan en `<h2>`. No hay `<h1>` en `<main>`.

**Fix:** Promover el título de página a `h1`; ajustar subheadings a `h2`/`h3`.

---

### Issue 8 — Tabla de historial sin `<caption>` ni `scope`
**WCAG:** 1.3.1 Info and Relationships (A) | **Severidad: MODERADA**

La `<table>` en `OrderHistory.tsx` no tiene `<caption>` y los `<th>` no tienen `scope="col"`.

**Fix:**
```tsx
<caption className="sr-only">Historial de órdenes</caption>
<th scope="col">Par</th>
<th scope="col">Bot</th>
// ...
```

---

### Issue 9 — PNL comunicado principalmente por color
**WCAG:** 1.4.1 Use of Color (A) | **Severidad: MODERADA**

En las tres páginas, ganancia/pérdida se indica con `text-green-500` / `text-red-500`. El signo numérico ayuda para el PNL total, pero badges y otros indicadores de estado difieren más por hue que por contenido textual.

**Fix sugerido:** Agregar `<span className="sr-only">Ganancia</span>` / `"Pérdida"` o usar íconos con `aria-label` junto al valor numérico.

---

### Issue 10 — Focus ring ausente en links de navegación
**WCAG:** 2.4.7 Focus Visible (AA) | **Severidad: MODERADA**

Los `Button` usan `focus-visible:ring-*` correctamente, pero los `Link` del nav (`AuthenticatedLayout.tsx`, `OrdersLayout.tsx`) no tienen estilo de focus explícito. Con el reset global `color: inherit` en `app.css`, el outline nativo puede perderse.

**Fix:**
```tsx
// Agregar a nav Links:
className="focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
```

---

### Issue 11 — Animación ping sin `prefers-reduced-motion`
**WCAG:** 2.3.3 Animation (AAA) | **Severidad: MENOR**

Los puntos de estado activo usan `animate-ping` continuo en `Bots.tsx` y `Positions.tsx` sin guard de `prefers-reduced-motion`.

**Fix:**
```css
@media (prefers-reduced-motion: reduce) {
  .animate-ping { animation: none; }
}
```
O en Tailwind: clase `motion-safe:animate-ping`.

---

### Issue 12 — Alt de íconos de criptomoneda poco descriptivo
**WCAG:** 1.1.1 Non-text Content (A) | **Severidad: MENOR**

`alt={baseCoin}` devuelve "btc", "eth", etc. — texto muy escueto y no en el idioma de la página.

**Fix:** `alt="Bitcoin"` o `alt=""` si el símbolo adyacente ya nombra el par.

---

### Issue 13 — Typo en título de página
**No es WCAG, es calidad**

`<Head title="Historial de Ordenes" />` — falta tilde.  
**Fix:** `"Historial de Órdenes"`

---

### Issue 14 — Separadores Radix pueden no transmitir contexto de lista
**WCAG:** 1.3.1 (bajo riesgo) | **Severidad: MENOR**

Separadores visuales en el header de stats. Estructura mayormente correcta para usuarios con visión; bajo riesgo de problema real.

---

## Qué está funcionando bien

- Landmarks semánticos: `<header>`, `<main>`, `<nav>`
- `<table>` real en historial con `<th>` textuales
- Badges Lado/Estado usan **texto** (no solo color)
- Menú hamburguesa con `sr-only` "Menu" y "Cambiar tema"
- Paginación con `<button>` semánticos y estado disabled
- `AlertDialog` de Radix con título/descripción (patrón correcto)
- Toast region con `"Notifications alt+T"` anunciado

---

## Prioridad de remediación

### Inmediata (antes de marcar Orders como accesible)
1. Acceso mobile a las 3 rutas (Issue 1)
2. Nombres en links de Positions (Issue 2)
3. Sort con teclado + `aria-sort` (Issue 3)
4. Labels correctos en filtros (Issue 4)
5. `APP_LOCALE=es` → `lang="es"` en HTML (Issue 5)

### Corto plazo
6. Skip link (Issue 6)
7. `h1` + jerarquía de headings (Issue 7)
8. `<caption>` + `scope="col"` en tabla (Issue 8)
9. Focus ring en nav links (Issue 10)
10. Alternativa textual al color en PNL (Issue 9)

### Mantenimiento continuo
- `prefers-reduced-motion` en animaciones (Issue 11)
- Alt de íconos (Issue 12)
- Typo en título (Issue 13)

---

## Veredicto: ❌ DOES NOT CONFORM (WCAG 2.2 AA)

Issues críticos y serios deben resolverse antes de declarar conformidad de accesibilidad para esta sección.
