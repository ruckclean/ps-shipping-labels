# Ruckclean Shipping Labels

PrestaShop module for printing shipping labels on thermal printers. Designed for postal mail shipping of small items like NFC keychains.

## Features

- 🖨️ **Multiple printer formats**: PDF (universal), ESC/POS (thermal), ZPL (Zebra)
- 📋 **Batch printing**: Print multiple labels at once
- 🔄 **Auto status update**: Automatically mark orders as shipped when labels are printed
- 🔌 **API access**: External batch operations via REST API
- ⚙️ **Fully configurable**: Label size, fonts, sender info, printer connection

## Installation

1. Download or clone this repository
2. Rename the folder to `rklabels`
3. Upload to `modules/` directory of your PrestaShop installation
4. Go to Modules > Module Manager and install "Ruckclean Shipping Labels"

## Configuration

After installation, go to **Modules > Ruckclean Shipping Labels > Configure**

### Printer Settings

| Setting | Description |
|---------|-------------|
| Printer Type | PDF (any printer), ESC/POS (thermal), or ZPL (Zebra) |
| Connection Type | USB, Network (IP), or Bluetooth |
| Printer IP | For network printers only |
| Printer Port | Default: 9100 |

### Label Settings

| Setting | Description |
|---------|-------------|
| Label Width | Width in mm (default: 100) |
| Label Height | Height in mm (default: 60) |
| Font Size | Font size for text (default: 12) |

### Sender Settings

| Setting | Description |
|---------|-------------|
| Show Sender | Include sender address on label |
| Sender Name | Your company name |
| Sender Address | Street address |
| Sender Postcode | Postal code |
| Sender City | City |

### Automation

| Setting | Description |
|---------|-------------|
| Auto-change status | Automatically change order status on print |
| Status after printing | Which status to set (e.g., "Shipped") |

## Usage

### Single Label

1. Go to **Orders > Orders**
2. Click on an order
3. Find the "Shipping Label" card
4. Click **Print Label**

### Batch Printing

1. Go to **Shipping > Shipping Labels**
2. Select orders to print
3. Click **Print Selected Labels**

## API Usage

The module includes a REST API for external integrations and batch operations.

### Authentication

All API calls require the `api_key` parameter. Find your API key in the module configuration under the "API" tab.

### Endpoints

Base URL: `https://yourstore.com/admin-xxx/index.php?controller=AdminRkLabels&action=api`

#### Get Pending Orders

```bash
curl "https://yourstore.com/admin-xxx/index.php?controller=AdminRkLabels&action=api&api_key=YOUR_KEY&api_action=pending"
```

Response:
```json
{
  "success": true,
  "orders": [
    {
      "id_order": 123,
      "reference": "ABCDEF",
      "date_add": "2025-02-26 10:30:00",
      "recipient_name": "John Doe",
      "address1": "123 Main St",
      "postcode": "28001",
      "city": "Madrid"
    }
  ]
}
```

#### Print Labels

```bash
curl "https://yourstore.com/admin-xxx/index.php?controller=AdminRkLabels&action=api&api_key=YOUR_KEY&api_action=print&ids=123,124,125"
```

Response:
```json
{
  "success": true,
  "results": [
    {"id": 123, "status": "printed", "label": {...}},
    {"id": 124, "status": "printed", "label": {...}},
    {"id": 125, "status": "error", "message": "Order not found"}
  ]
}
```

#### Get Status

```bash
curl "https://yourstore.com/admin-xxx/index.php?controller=AdminRkLabels&action=api&api_key=YOUR_KEY&api_action=status"
```

Response:
```json
{
  "success": true,
  "version": "1.0.0",
  "pending_count": 5
}
```

## Label Sizes

Common label sizes for shipping:

| Size | Use Case |
|------|----------|
| 100x60mm | Standard shipping label |
| 100x50mm | Compact shipping label |
| 89x36mm | Address label (DYMO compatible) |
| 57x32mm | Small label |

## Supported Printers

### PDF (Universal)
Works with any printer via browser print dialog.

### ESC/POS
Most thermal receipt printers:
- Epson TM series
- Star TSP series
- Generic Chinese thermal printers (58mm, 80mm)

### ZPL
Zebra and compatible:
- Zebra GK420d, ZD220, ZD420
- TSC printers
- Honeywell PC series

## Requirements

- PrestaShop 1.7.x or 8.x
- PHP 7.2+
- TCPDF (included in PrestaShop)

## File Structure

```
rklabels/
├── rklabels.php              # Main module file
├── config.xml                # Module configuration
├── controllers/
│   └── admin/
│       └── AdminRkLabelsController.php
├── classes/
│   ├── RkLabelPDF.php        # PDF generator
│   ├── RkLabelESCPOS.php     # ESC/POS generator
│   └── RkLabelZPL.php        # ZPL generator
├── views/
│   └── templates/
│       └── admin/
│           ├── order_button.tpl
│           ├── orders_list_button.tpl
│           └── pending_orders.tpl
└── README.md
```

## License

MIT License - Free to use and modify.

## Support

Created by Ruckclean for internal use. For questions or issues, contact [info@ruckclean.com](mailto:info@ruckclean.com).
