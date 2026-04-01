# Reality Check Report — Rex: Binance Accounts + Profile

**Fecha:** 29 Mar 2026  
**Agente:** Rex (Reality Checker — stops fantasy approvals)  
**Páginas:** `/binance-accounts`, `/profile` (con Telegram)  
**Veredicto:** ❌ NEEDS WORK (default — no hay evidencia suficiente de "listo")

---

## Binance Accounts (`/binance-accounts`)

### Qué está construido (realidad)

- Título **"Cuentas Binance"**, subtítulo en español: "Configurá tus API Keys para operar".
- Lista en tarjetas cuando hay cuentas. En staging: **"Testnet BTC"** con badges `Testnet` / `Activa`, recuento de bots, **API Key enmascarada** (prefijo + sufijo visibles).
- **"Última conexión"**: valor en **inglés** (`2 weeks ago`) — Carbon no tiene el locale español.
- **"+ Agregar Cuenta"** expande un bloque formulario inline (no abre modal separado).
- Formulario etiquetado como **"Nueva Cuenta de Binance"** con nota de encriptación.
- Acciones por cuenta: botón **"Test"** (inglés), **"Ver balance"**, icono papelera (eliminar).
- Eliminar no se ejecutó (acción destructiva); el control existe.

### Form & Validación

| Campo | Tipo HTML | Estado |
|-------|-----------|--------|
| Nombre/Etiqueta | text | Labels en español ✅ |
| API Key | `type="password"` | ✅ enmascarado al escribir |
| API Secret | `type="password"` | ✅ enmascarado al escribir |
| Testnet checkbox | checkbox | ✅ en español |

**Envío vacío:** El botón pasó a "Guardando…" y el servidor devolvió errores de validación en **inglés**:
```
The label field is required.
The api key field is required.
The api secret field is required.
```

**Incumple el requisito de UI 100% en español.** Fix requerido: `lang/es/validation.php` + atributos personalizados en el Form Request.

### Seguridad — Hallazgos

| Aspecto | Estado | Notas |
|---------|--------|-------|
| API Key en texto plano en lista | ✅ NO expuesta | Solo `masked_api_key` en respuesta Inertia |
| Respuesta backend con clave completa | ✅ NO | `api_key` / `api_secret` están en `$hidden` del modelo |
| Envío del formulario | ⚠️ Solo HTTPS | Las credenciales van en el POST — correcto si hay TLS en producción; verificar que no corra en HTTP |
| API Key de la app (Perfil → "Mostrar") | ⚠️ MODERADO | Se muestra en texto plano al hacer clic. Es diseño intencional pero sin auto-ocultado tras timeout |

**No hay evidencia de exposición de claves de Binance.** El riesgo de API key de la app es voluntario al hacer clic pero recomendable agregar auto-ocultado.

### Test de Conexión

- Botón pasó a **"Probando…"** (español, con loading) ✅
- Resultado mostrado: **"Conexión exitosa"** ✅
- **Problema UX:** El feedback de éxito se pintó con **estilo visual de error** (apariencia rojo/alerta en la tarjeta) — contradice el mensaje positivo. Riesgo de confundir al usuario.

### Ver Balance

- Tras la petición se mostró balance USDT con formato `"0,00 disp."` ✅
- Coherente con el idioma español.

### Issues encontrados — Binance Accounts

| Severidad | Issue | Fix |
|-----------|-------|-----|
| 🔴 Alta | Mensajes de validación en inglés | `lang/es/validation.php` + `APP_LOCALE=es` |
| 🟡 Media | "Última conexión" en inglés (`2 weeks ago`) | `Carbon::setLocale('es')` en `AppServiceProvider` |
| 🟡 Media | Botón "Test" en inglés | Cambiar a "Probar" o "Probar conexión" |
| 🟡 Media | Badge "Testnet" en inglés | Cambiar a "Red de prueba" o mantener como término técnico aceptado |
| 🟡 Media | Éxito de conexión con estilo visual de error | Usar `text-green-*` / `bg-green-*` para el mensaje de éxito |
| 🟢 Baja | Empty state no validado en esta corrida | Verificar que tenga copy de onboarding en español |

---

## Profile (`/profile`)

### Estructura de pestañas

```
Perfil | Contraseña | Telegram | API Key | Eliminar
```

### Pestaña Perfil

- Campos: Nombre, Email. Botón "Guardar" en español. ✅
- Barra superior sigue mezclando inglés ("Dashboard", "Trading", "AI Agent"). ⚠️

### Pestaña Contraseña

- Tres campos: contraseña actual, nueva, confirmar. Todos `type="password"`. ✅
- **Envío vacío devuelve errores en inglés:**
  ```
  The current password field is required.
  The password field is required.
  ```
  Mismo problema de `APP_LOCALE=en` que en Binance Accounts.

### Pestaña Eliminar Cuenta

- Advertencia en español. ✅
- Botón rojo "Eliminar Cuenta" abre modal de confirmación con:
  - "¿Estás seguro…?" en español ✅
  - Campo de contraseña para confirmar ✅
  - Botones "Cancelar" / "Eliminar Cuenta" en español ✅
- **Cumple correctamente el patrón de confirmación explícita.**

---

## Telegram Integration

### Estado en staging: CONECTADO

- Caja verde "Telegram conectado", Chat ID visible, lista de eventos en español. ✅
- Instrucciones para activar en AI Agent presentes. ✅
- Sin código QR (no aplica en estado conectado).
- Botones: "Enviar prueba" y "Desconectar" (rojo).

### Issues encontrados — Telegram

| Severidad | Issue | Fix |
|-----------|-------|-----|
| 🟡 Media | Chat ID visible en pantalla cuando está conectado | Considerar enmascarado parcial (ej: `****1234`) |
| 🟡 Media | `disconnect` usa `confirm()` nativo del browser (no modal ShadCN) | Reemplazar por `AlertDialog` de Radix |
| 🟡 Media | Partes del flujo usan `alert()` nativo (error handling) | Reemplazar por `toast()` del sistema de notificaciones |
| 🟢 Baja | Estado "no configurado" / flujo de vinculación no validado en esta sesión | Verificar flow desconectado manualmente |

### Nota sobre el flujo desconectado (revisión de código)

`TelegramConfig.tsx` implementa: generación de token → deep link a Telegram → polling de confirmación + modo manual de Chat ID. No fue probado en UI en esta sesión (solo revisión de código). **Pendiente de verificación.**

---

## Evaluación general de integración

| Aspecto | Estado |
|---------|--------|
| Estados de carga en acciones | ✅ "Guardando…", "Probando…", "Enviando…" presentes |
| Validación funciona | ✅ (pero en inglés) |
| Feedback de éxito/error | ⚠️ Test de Binance con color incorrecto; Telegram usa alerts nativos |
| Errores de consola | ⚠️ Recharts en Dashboard (compartido); sin errores adicionales en Binance/Profile |
| API keys de Binance protegidas | ✅ Solo `masked_api_key` en respuestas |

---

## Veredicto: ❌ NEEDS WORK

La integración **funciona en líneas generales** pero con fallos claros de localización y detalles de UX.

## Fixes requeridos antes de producción

1. **`lang/es/validation.php`** + atributos de Form Requests en español (afecta Binance y Profile/Password).
2. **`Carbon::setLocale('es')`** en `AppServiceProvider::boot()` para fechas relativas.
3. Renombrar **"Test"** → "Probar conexión" (o traducción apropiada).
4. Ajustar **estilos del mensaje de éxito** en tarjeta Binance (tokens semánticos de color verde).
5. **Telegram:** reemplazar `alert()`/`confirm()` por `AlertDialog` / `toast()` de ShadCN.
6. Decidir si "Dashboard", "Trading", "AI Agent" deben pasar a español (afecta toda la app).
7. (Opcional) Auto-ocultar API Key de la app tras X segundos de mostrada.
