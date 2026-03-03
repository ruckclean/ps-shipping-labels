# Ruckclean Shipping Labels

Módulo PrestaShop para impresión de etiquetas de envío postal de llaveros NFC.

## Funcionalidades

- Generación de etiquetas en PDF, ESC/POS (térmicas) y ZPL (Zebra)
- Impresión individual o por lotes
- Cambio automático de estado del pedido al imprimir
- API para integración externa
- Configuración de remitente

## Instalación

1. Subir carpeta `rklabels` a `/modules/`
2. Instalar desde BackOffice → Módulos
3. Configurar en Módulos → Ruckclean Shipping Labels → Configurar

## Configuración

| Pestaña | Descripción |
|---------|-------------|
| Impresora | Tipo de impresora y conexión |
| Etiqueta | Dimensiones y tamaño de fuente |
| Remitente | Datos del remitente (Ruckclean) |
| Automatización | Cambio automático de estado |
| API | Clave para acceso externo |

## API REST

Base URL: `/index.php?fc=module&module=rklabels&controller=api&api_key=TU_API_KEY`

| Acción | Descripción |
|--------|-------------|
| `status` | Estado del módulo |
| `pending` | Pedidos pendientes de etiqueta |
| `order&id_order=X` | Datos de un pedido |
| `print&ids=1,2,3` | Marcar como impresos |

## Uso

1. Ve a un pedido → Panel "Etiqueta de Envío Postal"
2. Click "Imprimir Etiqueta"
3. Se descarga PDF con los datos del destinatario

Para lotes: Envío → Etiquetas Envío → Seleccionar pedidos → Imprimir

## Versiones

- **1.3.0** - Solo etiquetas (lockers movidos a rkpickup)
- **1.2.0** - Añadido sistema de lockers (deprecated)
- **1.0.0** - Versión inicial

## Relacionado

Para gestión de taquillas/lockers de recogida, usar el módulo **rkpickup** (Ruckclean TTLock Pickup).

---

GitHub: https://github.com/ruckclean/ps-shipping-labels
