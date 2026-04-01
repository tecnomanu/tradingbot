# UI Design Audit — Vera: Full Visual Review

**Fecha:** 29 Mar 2026  
**Agente:** Vera (UI Designer — ShadCN/UI + Tailwind specialist)  
**Alcance:** Todas las vistas de la aplicación  
**Score:** B — Good (no Excellent)

---

## Design System Assessment

### Paleta de colores

Paleta **oscuro profesional** coherente con trading:
- Fondo: negro/carbón con `card` ligeramente más clara y bordes sutiles
- **Verde:** beneficio, CTAs, estados "activo"
- **Rojo:** ventas, parar bot, acciones destructivas
- **Púrpura:** IA
- **Ámbar/amarillo:** avisos (banner de edición)
- Iconos de KPI: azul/naranja/púrpura para diferenciar bloques

⚠️ Los múltiples colores de iconos de KPI añaden "ruido cromático" vs. un sistema más estricto con solo semántica + neutros.

### Tipografía

Sans moderna al estilo ShadCN — peso y tamaño bien escalados:
- Títulos en blanco, etiquetas en gris `muted`, cifras en negrita
- Jerarquía clara en tablas de órdenes
- ⚠️ En zonas muy densas (terminal, pie de tarjetas de posición): texto secundario pequeño y de bajo contraste — resta legibilidad en monitores modestos

### Uso de componentes ShadCN

ShadCN/UI + Tailwind se percibe consistentemente: `Card`, inputs, botones, `Tabs`, `Badge`, `Select`, diálogos/menús. Mismos radio de borde y estilo en la mayoría de pantallas.

⚠️ Integraciones pesadas (TradingView, libro de órdenes) rompen homogeneidad cuando quedan en "Cargando…" / guiones.

### Spacing

Dashboard y Actividad: ritmo vertical y grids de tarjetas alineados. ⚠️ Posiciones y Cuentas Binance con pocos ítems tienen mucho vacío bajo el contenido (no es un empty state diseñado, es "página corta"). El terminal es deliberadamente denso — aceptable para trading.

---

## Revisión página por página

### Login Page
**Calidad: Alta** — por encima de un CRUD genérico.

- Split layout: marca a la izquierda (claim, bullets con iconos) + card de acceso a la derecha
- Glow verde suave — atmósfera "fintech" sin ser caricatura
- Jerarquía clara (H1 + subtítulo + formulario)
- Botón con estado "Ingresando…" + disabled: buen detalle de pulido

**Issues:**
- Posible inconsistencia ortográfica en el pie ("Registrate" vs. con tilde en otros contextos)
- Copy panel izquierdo: "Charts, order book…" en inglés

---

### Dashboard
**Calidad: Buena** — vista de producto trading con densidad adecuada.

- Fila de KPIs, gráfico de evolución PnL, ejecuciones recientes, bots, log IA, resumen cuentas

**Issues:**
- Gráfico a veces plano/vacío según rango de datos — sin empty state explicativo
- Mezcla de inglés en métricas secundarias ("13m ago", "exec")
- KPIs con muchos colores de icono puede distraer del mensaje financiero principal

---

### Bot Management (`/bots` — Trading Terminal)
**Calidad: Seria** — terminal profesional con chart, libro, formulario, tabla.

**Issues:**
- Ticker y chart con placeholders (`--`, "Cargando…") durante carga: poco diseñado como estado; resta confianza
- Botones "Depositar" / "Transfer" mezclan español e inglés
- Panel derecho + overlay "Preview" puede superponerse al chart y sentirse cargado
- Botón "Crear" muy dominante vs. otras acciones (coherente con CTA pero desbalanceado)

---

### Bot Detail (`/bots/1`) y Edición (`/bots/1/edit`)
**Calidad: Buena.**

- Cabecera con título, badges (Activo, Futures, Largo, leverage), acciones claras
- Métricas en fila de cards; tabs numerosas pero legibles
- Tablas de pendientes/ejecuciones con Compra/Venta en verde/rojo: correcto para trading

**Issues:**
- Misma fricción de chart en carga
- Banner ámbar de "modo edición" útil pero muy técnico — podría refinarse tipográficamente
- "Guardar cambios" aparece disabled sin hint de validación visible → frustración potencial

---

### Orders — Grid Bots (`/orders/bots`)
**Calidad: Funcional.**

- Sub-nav lateral muy claro
- Cards de bot con información densa (margen, rejilla, rondas, liquidación)

**Issues:**
- Mucho espacio horizontal vacío entre columnas dentro de cada card en pantallas anchas
- PNL en resumen de cabecera con color poco semántico en algunas capturas (naranja/rojizo frente a verde en el cuerpo) — revisar tokens

---

### Orders — History (`/orders/history`)
**Calidad: Lo más pulido de la sección.** — estilo "mesa de operaciones".

- Filtros en fila, "Exportar", tabla densa con badges Compra/Venta y PNL coloreado
- Números con separadores y decimales largos apropiados para crypto

**Issues:**
- Título: **"Historial de Ordenes"** — **falta tilde**: debe ser "**Órdenes**"
- Paginación: confirmar estados `disabled` claros para "Anterior" en primera página

---

### Orders — Positions (`/orders/positions`)
**Calidad: DEFICIENTE — problema crítico de confianza.**

**Issue crítico:**
En el **encabezado** aparece PNL total positivo en **rojo**, mientras en las tarjetas individuales el mismo PNL va en **verde**. Esto:
- Contradice la semántica universal de trading (rojo = pérdida)
- Parece un **error de clase CSS o de componente**, no una decisión de diseño
- **Erosiona la confianza del trader en los datos mostrados**

Otros issues:
- Cards con texto superpuesto / ilegible y gran área vacía a la derecha en desktop
- Bajo contraste del texto meta en pies de tarjeta

---

### AI Agent (`/ai-agent`)
**Calidad: Funcional pero con roces.**

- Hero con icono, métricas, tabs, lista con badges de estado
- Pestañas bien diferenciadas

**Issues:**
- Strings en inglés crudo ("Agent did not complete analysis") suenan a **error de producto**, no a mensaje para el usuario
- Contenido de historial muy largo en una sola columna: valorar truncado + expand o paginación
- Posible doble región de accesibilidad ("Notifications" duplicado en árbol)

---

### Binance Accounts (`/binance-accounts`)
**Calidad: Funcional y limpio.**

- H1 + subtítulo + CTA "Agregar Cuenta"; card con badges; acciones visibles

**Issues:**
- "2 weeks ago" en inglés
- Con una sola cuenta: layout vacío a la derecha (sin ilustración ni CTA secundario)
- Botón "Test" ambiguo para usuarios no técnicos

---

### Profile (`/profile`)
**Calidad: Funcional — más settings genérico que producto trading.**

- Tabs horizontales, formulario simple, "Guardar" verde

**Issues:**
- H2 "Perfil" en barra superior + tab "Perfil" → redundancia
- Menos "marca trading" que el resto del producto — aceptable para settings

---

## Issues de Consistencia Cross-Page

| Área | Observación |
|------|-------------|
| **Nav activo** | Mezcla de pill/background (Dashboard, sidebar Actividad) vs. subrayado/texto (Trading, top nav) — unificar patrón |
| **Nivel de título** | `<h1>` en algunas vistas, `<h2>` en otras para el título principal de página |
| **i18n** | Inglés en: nav, "Transfer", timestamps relativos, mensajes de agente, posiblemente tooltips |
| **Marca** | "GridBot" en UI vs "TradingBot" en documentación/brief |
| **Dropdown usuario** | Fondo semitransparente con contenido de detrás "sangrando" — resta nitidez |
| **Semántica de color** | Bug PNL+ en rojo en Posiciones (y posible tono incorrecto en resumen de Grid Bots) |

---

## Diseño específico para trading

| Aspecto | Estado |
|---------|--------|
| PNL y lado en tablas | ✅ Verde/rojo y badges Compra/Venta correctamente alineados |
| Formato numérico | ✅ Miles con coma, 8 decimales apropiados para crypto |
| Estados de bot | ✅ Punto verde, "Activo", badges de modo y dirección claros |
| Gráficos | ⚠️ Leyendas razonables; experiencia de **carga del chart** debilita percepción de datos en vivo |
| PNL en Posiciones (header) | ❌ **Bug crítico: positivo en rojo** |

---

## Score general: B

- **B+ en ejecución ShadCN** — consistencia visual buena, componentes bien aplicados
- **Penalizado por:** i18n, bug de color en PNL agregado, estados de carga/toast menos cuidados
- **Nivel de diseño: Good** — por encima de un admin Laravel típico, con intención clara de producto trading
- **No Excellent** hasta corregir semántica financiera, copy unificado y estados vacíos/carga del chart

---

## Prioridades de mejora de diseño

### Crítico (roto o muy inconsistente)
1. **Bug PNL+ en rojo** en Posiciones — fix inmediato en `Positions.tsx` header (tokens semánticos `text-success` vs `text-destructive`)
2. **Traducir "Agent did not complete analysis"** y otros strings en inglés en flujos visibles
3. **"Historial de Ordenes"** → **"Historial de Órdenes"** (agregar tilde)

### Medio (inconsistente pero funcional)
1. Unificar patrón de item activo en nav principal y subnav de Actividad
2. Normalizar jerarquía de título (siempre `h1` para título de página)
3. Español completo: "Transfer" → "Transferir", fechas relativas localizadas ("hace 2 semanas")
4. Dropdown usuario: fondo sólido para evitar bleed del contenido de detrás

### Pulido (mejoras menores)
1. Empty states ilustrados con CTA en Cuentas Binance, Posiciones con pocos bots, chart sin datos
2. Skeletons o layout estable para TradingView y fila de precios (en lugar de solo "Cargando…" y guiones)
3. Revisar contraste del texto meta en pies de tarjeta de posiciones
4. Ajustar densidad/espaciado en cards de Grid Bots en viewport ancho (evitar "túnel" vacío entre columnas)
