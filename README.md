# Ruckclean Shipping Labels & Lockers

Módulo PrestaShop para impresión de etiquetas de envío y gestión de lockers de recogida para llaveros NFC.

## Características

### Etiquetas de Envío
- Generación de etiquetas en PDF, ESC/POS (térmicas) y ZPL (Zebra)
- Impresión individual o por lotes
- Cambio automático de estado del pedido al imprimir
- API para integración externa

### Lockers de Recogida
- **Dashboard visual** con estado de cada locker
- **Asignación round-robin** automática en nuevos pedidos
- **Gestión de estados**: Asignado → Listo → Recogido
- **Generación de PIN** temporal para apertura
- **Log de eventos** por pedido y locker
- **Preparado para TTLock API** (integración futura)

## Instalación

1. Subir carpeta `rklabels` a `/modules/`
2. Instalar desde BackOffice → Módulos
3. Configurar en Módulos → Ruckclean → Configurar

Si ya tenías el módulo instalado, desinstala y reinstala para crear las tablas de lockers.

## Configuración

### Pestañas de Configuración

| Pestaña | Descripción |
|---------|-------------|
| Impresora | Tipo de impresora y conexión |
| Etiqueta | Dimensiones y tamaño de fuente |
| Remitente | Datos del remitente |
| Lockers | Asignación automática y estados |
| TTLock API | Credenciales para apertura remota |
| Automatización | Cambio automático de estados |
| API | Clave para acceso externo |

### Opciones de Lockers

- **Asignar locker automáticamente**: Asigna un locker cuando se crea un pedido
- **Asignar a todos los pedidos**: Si no, solo a pedidos con método "recogida"
- **Estado "Recogido"**: Estado que libera el locker
- **Validez del PIN**: Horas que el PIN es válido

## Dashboard de Lockers

Accesible desde **BackOffice → Ruckclean → Lockers**

Muestra:
- Estadísticas (disponibles/ocupados/recogidos hoy)
- Estado visual de cada locker (disponible/ocupado)
- Enlace directo al pedido asignado
- Botón para liberar locker manualmente
- Actividad reciente

## Vista en Pedido

Desde la vista de un pedido individual:
- Información del locker asignado
- Estado y PIN
- Botones: Marcar Listo / Marcar Recogido / Cancelar
- Historial de eventos del locker

## API REST

Base URL: `/index.php?fc=module&module=rklabels&controller=api&api_key=TU_API_KEY`

### Endpoints de Etiquetas

| Acción | Descripción |
|--------|-------------|
| `status` | Estado del módulo |
| `pending` | Pedidos pendientes |
| `order&id_order=X` | Datos de un pedido |
| `print&ids=1,2,3` | Marcar como impresos |

### Endpoints de Lockers

| Acción | Descripción |
|--------|-------------|
| `lockers` | Lista de lockers con estado |
| `locker_assign&id_order=X` | Asignar locker a pedido |
| `locker_ready&id_order=X&pin_code=123456` | Marcar listo para recogida |
| `locker_collected&id_order=X` | Marcar como recogido |
| `locker_status&id_order=X` | Estado del locker de un pedido |
| `locker_logs&id_order=X` | Historial de eventos |

### Ejemplos

```bash
# Ver estado de lockers
curl "http://tutienda.com/index.php?fc=module&module=rklabels&controller=api&api_key=TU_KEY&api_action=lockers"

# Asignar locker a pedido
curl "http://tutienda.com/index.php?fc=module&module=rklabels&controller=api&api_key=TU_KEY&api_action=locker_assign&id_order=5"

# Marcar como listo con PIN
curl "http://tutienda.com/index.php?fc=module&module=rklabels&controller=api&api_key=TU_KEY&api_action=locker_ready&id_order=5&pin_code=123456"

# Marcar como recogido
curl "http://tutienda.com/index.php?fc=module&module=rklabels&controller=api&api_key=TU_KEY&api_action=locker_collected&id_order=5"
```

## Flujo de Trabajo

```
1. Cliente hace pedido
   ↓
2. [Auto] Se asigna locker disponible (round-robin)
   ↓
3. Depositas llavero en locker
   ↓
4. Marcas "Listo" → Se genera PIN → Se notifica cliente
   ↓
5. Cliente recoge (con PIN o TTLock)
   ↓
6. Marcas "Recogido" → Locker queda libre
```

## Estructura de Base de Datos

### rk_locker
Configuración de los lockers físicos.

| Campo | Tipo | Descripción |
|-------|------|-------------|
| id_locker | INT | ID único |
| name | VARCHAR | Nombre (Locker 1, etc) |
| ttlock_lock_id | VARCHAR | ID en TTLock API |
| location | VARCHAR | Ubicación física |
| active | BOOL | Activo/inactivo |
| position | INT | Orden para round-robin |

### rk_locker_assignment
Asignaciones de lockers a pedidos.

| Campo | Tipo | Descripción |
|-------|------|-------------|
| id_assignment | INT | ID único |
| id_locker | INT | FK a rk_locker |
| id_order | INT | FK a orders |
| status | ENUM | assigned/ready/collected/cancelled |
| pin_code | VARCHAR | PIN de acceso |
| pin_valid_until | DATETIME | Expiración del PIN |
| date_assigned | DATETIME | Fecha asignación |
| date_ready | DATETIME | Fecha listo |
| date_collected | DATETIME | Fecha recogida |

### rk_locker_log
Historial de eventos para auditoría.

| Campo | Tipo | Descripción |
|-------|------|-------------|
| id_log | INT | ID único |
| id_locker | INT | FK a rk_locker |
| id_order | INT | FK a orders |
| event_type | VARCHAR | Tipo de evento |
| event_data | JSON | Datos adicionales |
| ip_address | VARCHAR | IP del usuario |
| id_employee | INT | Empleado (si aplica) |
| date_add | DATETIME | Fecha del evento |

## TTLock (Futuro)

El módulo está preparado para integrarse con TTLock API:

1. Registrarse en https://euopen.ttlock.com/register
2. Obtener Client ID y Secret
3. Configurar en pestaña TTLock API
4. Vincular ID de cada cerradura en configuración de lockers

## Versiones

- **1.1.0** - Añadido sistema de lockers
- **1.0.0** - Versión inicial con etiquetas

## Soporte

Desarrollado por Ruckclean para gestión de lavado de coches self-service.

GitHub: https://github.com/ruckclean/ps-shipping-labels
