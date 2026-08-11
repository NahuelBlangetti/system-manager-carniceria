<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }

    body {
        font-family: DejaVu Sans, sans-serif;
        font-size: {{ $fontSize ?? '10px' }};
        color: #1a1a1a;
        background: #fff;
    }

    .page {
        padding: {{ $pagePadding ?? '28px 32px' }};
    }

    .header {
        display: table;
        width: 100%;
        border-bottom: 2px solid {{ $store['primary_color'] }};
        padding-bottom: 12px;
        margin-bottom: 18px;
    }

    .header-logo {
        display: table-cell;
        vertical-align: middle;
        width: 130px;
    }

    .header-logo img {
        max-height: 48px;
        max-width: 120px;
    }

    .header-info {
        display: table-cell;
        vertical-align: middle;
        text-align: right;
    }

    .store-name {
        font-size: 13px;
        font-weight: bold;
        color: #1a1a1a;
        letter-spacing: 0.3px;
    }

    .store-tagline {
        font-size: 8px;
        color: #666;
        margin-top: 1px;
    }

    .header-title {
        font-size: 15px;
        font-weight: bold;
        color: {{ $store['primary_color'] }};
        letter-spacing: 0.5px;
        margin-top: 4px;
    }

    .header-subtitle {
        font-size: 9px;
        color: #666;
        margin-top: 2px;
    }

    .confidential,
    .audience-badge {
        display: inline-block;
        background: #fef3c7;
        color: #92400e;
        font-size: 7px;
        font-weight: bold;
        padding: 2px 6px;
        border-radius: 10px;
        margin-top: 4px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .audience-badge {
        background: #fff7ed;
        color: #c2410c;
    }

    table {
        width: 100%;
        border-collapse: collapse;
    }

    thead tr {
        background-color: {{ $store['primary_color'] }};
        color: #fff;
    }

    thead th {
        padding: {{ $thPadding ?? '7px 8px' }};
        text-align: left;
        font-size: {{ $thFontSize ?? '9px' }};
        font-weight: bold;
        text-transform: uppercase;
        letter-spacing: 0.4px;
    }

    thead th.right  { text-align: right; }
    thead th.center { text-align: center; }

    tbody tr:nth-child(even) { background-color: #fafafa; }
    tbody tr:nth-child(odd)  { background-color: #fff; }

    tbody td {
        padding: {{ $tdPadding ?? '6px 8px' }};
        border-bottom: 1px solid #ebebeb;
        vertical-align: middle;
    }

    tbody td.right  { text-align: right; }
    tbody td.center { text-align: center; }

    .badge {
        display: inline-block;
        padding: 2px 6px;
        border-radius: 10px;
        font-size: {{ $badgeFontSize ?? '8px' }};
        background-color: #e5e7eb;
        color: #374151;
    }

    .stock-ok      { background-color: #d1fae5; color: #065f46; }
    .stock-warning { background-color: #fef3c7; color: #92400e; }
    .stock-danger  { background-color: #fee2e2; color: #991b1b; }

    .margin-ok      { background-color: #d1fae5; color: #065f46; }
    .margin-warning { background-color: #fef3c7; color: #92400e; }
    .margin-danger  { background-color: #fee2e2; color: #991b1b; }

    .price  { font-weight: bold; color: #1a1a1a; }
    .cost   { color: #6b7280; }
    .no-data { color: #aaa; font-style: italic; }

    .footer {
        margin-top: 18px;
        border-top: 1px solid #e5e7eb;
        padding-top: 8px;
        display: table;
        width: 100%;
    }

    .footer-left {
        display: table-cell;
        font-size: 8px;
        color: #666;
        vertical-align: top;
        width: 70%;
    }

    .footer-contact {
        margin-top: 3px;
        color: #888;
    }

    .footer-right {
        display: table-cell;
        text-align: right;
        font-size: 8px;
        color: #888;
        vertical-align: top;
    }
</style>
