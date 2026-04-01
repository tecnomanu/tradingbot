# QA Report — Eva: Bot Management

**Fecha:** 29 Mar 2026  
**Agente:** Eva (Evidence Collector — screenshot-obsessed QA)  
**URL probada:** `http://wizardgpt.tail100e88.ts.net:8100/bots`  
**Veredicto:** ❌ NEEDS WORK

---

## Observación general

`/bots` no es una lista simple: es la **Trading Terminal** completa — gráfico de precios, libro de órdenes, panel de creación lateral (Futuros/Spot), y tabla de bots al fondo. El nav muestra "Trading" como activo.

---

## Screenshots capturados

| Archivo | Descripción |
|--------|-------------|
| `eva-bots-list-desktop.png` | `/bots`: terminal con gráfico, formulario lateral, tabla de bots, filtros |
| `eva-bot-detail-1.png` | `/bots/1`: cabecera, KPIs, pestañas, gráfico En Vivo, tablas |
| `eva-bot-edit-form.png` | `/bots/1/edit`: banner amarillo "Modo edición", formulario "Editar Bot" |
| `eva-create-validation-rejillas.png` | Formulario con rejilla=1 y error de validación en español |
| `eva-bot-parametros-tab.png` | Detalle bot → pestaña "Parámetros" → "Configuración del Bot" |
| `eva-stop-bot-modal.png` | Modal "¿Detener bot BTC/USDT?" con texto y botones en español |
| `eva-bots-mobile-375.png` | Vista a 375×812px — layout responsive evaluado |

---

## Issues encontrados

### Issue 1 — Texto en inglés en UI visible
**Prioridad:** Medium (requisito explícito de producto)

Cadenas en inglés encontradas:
- Navegación: "Dashboard", "AI Agent"
- Overlay: "Preview Bot"
- Tipos de mercado: "Futures", "Perp", "Spot"
- Parámetros: "Leverage", "Grid", "Slippage"
- Nombres de bots: "Grid Bot" (en datos, aceptable como nombre propio)
- Botón de cuenta: "Transfer"
- Pestaña: "AI Agent" en detalle de bot
- Atribución TradingView: aceptable como marca

**Evidencia:** `eva-bots-list-desktop.png`, `eva-bot-detail-1.png`, `eva-bot-parametros-tab.png`

---

### Issue 2 — Botón "Crear" deshabilitado sin mensaje explicativo
**Prioridad:** Medium

Cuando el rango de precio inferior/superior es `0`, el botón "Crear" aparece `disabled` pero **no hay mensaje inline** explicando por qué no se puede crear. El usuario no sabe qué campo completar.

La validación de rejillas sí funciona correctamente (mensaje en español: *"La cantidad de rejillas debe estar entre 2 y 500"*), pero no el caso del formulario incompleto de precios.

**Evidencia:** Estados de formulario en `/bots` con spinbuttons en 0.

---

### Issue 3 — Pantalla en negro intermitente (posible fallo crítico)
**Prioridad:** Crítica si es reproducible en producción

En al menos una carga, el documento en `/bots` quedó con **snapshot vacío** y título genérico "GridBot Trading". Solo el reload recuperó la UI. Señal de:
- Fallo de hidratación Inertia/React
- Race condition en actualización de estado
- Error JS no capturado

**Evidencia:** Comportamiento observado en flujo; snapshot post-reload funcionó correctamente.

---

### Issue 4 — Inconsistencia de carga (race conditions)
**Prioridad:** Media-Alta

En varias visitas el área central mostró "Cargando…" extendido, libro vacío con `--`, y cuenta en 0 USDT frente a otras cargas con saldo ~5306 USDT. Indica condiciones de carrera en el estado de la aplicación.

**Evidencia:** Secuencia de snapshots durante el test.

---

### Issue 5 — Modal "Detener bot" no cierra al hacer clic en Cancelar
**Prioridad:** Media

Al pulsar **Cancelar**, el modal permaneció visible en el snapshot siguiente. Solo cerró con Escape.

**Evidencia:** Flujo post-click en Cancelar vs Escape (`eva-stop-bot-modal.png`).

---

### Issue 6 — Números con exceso de decimales en formulario de edición
**Prioridad:** Baja-Media

Los inputs de rango en edición muestran muchos decimales (ruido visual). La sección "3. Inversión" aparece poco clara respecto a editar capital.

**Evidencia:** `eva-bot-edit-form.png`

---

### Issue 7 — Pestaña "Parámetros" con mucho espacio vacío en desktop
**Prioridad:** Baja

El bloque "Configuración del Bot" ocupa poco ancho; hay gran área vacía a la derecha en viewport desktop.

**Evidencia:** `eva-bot-parametros-tab.png`

---

### Issue 8 — Dos bots con el mismo nombre
**Prioridad:** Baja (UX)

Dos instancias "BTC/USDT Grid Bot" en la lista. Distinguirlas solo por PnL/órdenes es frágil.

**Fix sugerido:** Agregar sufijo numérico automático o permitir nombres únicos forzados.

---

## CRUD Testing

| Operación | Estado | Notas |
|-----------|--------|-------|
| Create | ⚠️ PARCIAL | Validación client-side funciona en español. No se envió creación real (botón disabled con rango=0). |
| Read/List | ✅ PASS | Tabla con filtros, columnas en español, 2 bots activos. |
| Edit | ✅ PASS | `/bots/1/edit` con banner de edición, "Guardar cambios" / cancelar. |
| Delete | ⚠️ NO PROBADO | Icono papelera presente; no se ejecutó para no borrar datos de staging. |

---

## Start/Stop Testing

| Acción | Estado | Notas |
|--------|--------|-------|
| Stop | ⚠️ PARCIAL | Modal de confirmación en español con buena descripción. No se confirmó para no parar grid en vivo. Bug: Cancelar no cierra el modal. |
| Start | ⚠️ NO PROBADO | Bots ya activos; sin bot detenido para reactivar. |

---

## Spanish Language Check

**FALLA:** Hay inglés sustancial en navegación, preview, tipos de mercado, parámetros técnicos y botones funcionales.  
**PASA:** Modal de parada, formularios de validación, labels de tabla/columnas, acciones de bot.

---

## Mobile Responsive (375px)

**FALLA:** A 375×812px la vista sigue siendo el layout tipo terminal de escritorio (gráfico + panel + tabla). No hay adaptación mobile-first (stack vertical, drawer único, tabla en cards). **Riesgo alto de usabilidad** en pantallas pequeñas.

---

## Errores de consola

- Error de Recharts en `/dashboard` (`AreaChart width/height -1`) — compartido desde la base de charts.
- Pantalla negra en `/bots` sin error JS visible capturado — sin explicación confirmada.

---

## Veredicto: ❌ NEEDS WORK

**Para llegar a PASS:**
1. Cerrar i18n (strings en inglés funcionales)
2. Mensaje explicativo cuando "Crear" está disabled
3. Fix modal Cancelar
4. Investigar y resolver pantalla negra
5. Diseñar layout responsive para mobile
