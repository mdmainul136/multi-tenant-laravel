# ⚖️ Sourcing Legally-Safe Manifesto (Retailer Model)

This document outlines the strict operational and technical rules for the Product Sourcing system. Following these rules transforms the system from a "scraper" into a **Cross-Border Importer & Retailer**, which is legally safer and more robust.

## 1. The Core Architecture
- **Internal Only**: The Python sourcing tool is for internal staff only. Customers **NEVER** provide URLs.
- **One-Time Import**: We "ingest" data once to speed up entry. We do NOT maintain a live mirror of foreign marketplaces.
- **Ownership**: Once imported, we own the SKU, the pricing, and the fulfillment responsibility.

## 2. Content Rules (Mandatory)
- **Rewrite Everything**: Do NOT copy descriptions or titles verbatim. Every listing must be rewritten (AI-assisted or manual) to create a unique value proposition.
- **Avoid Verbatim Risk**: Verification checks are built into the code. "Approve" will fail if the title is identical to the source.
- **Own Imagery**: Warehouse photos are the gold standard. Always aim to replace sourced images with own photography once the item arrives for QC.

## 3. Pricing Rules (The Locked Model)
- **Formula**: `Source Cost + Freight + Duty/VAT + Warehouse Cost + Margin = Local BDT Price`.
- **Price Lock**: Local selling prices are fixed at the time of publication. They do NOT auto-sync with Amazon/Walmart price changes.
- **No Mirroring**: We are retailers selling at our own price point, not a price-comparison service.

## 4. Kill-Switch Protocol
- **Domain Level**: One-click block for vendors/marketplaces that pose legal or quality risks.
- **SKU Level**: One-click disable for specific products that violate policies or trademarks.

## 5. Marketing & Identity
- **Who we are**: An "International Procurement and Local Fulfillment Retailer".
- **Focus**: We sell **Access, Authenticity, and Delivery**, not "Amazon data".
- **Forbidden**: Do not use marketplace logos (Amazon, Walmart, etc.) in ads. Do not claim to be an "Official Partner".

---
*By following these rules, we operate as a legitimate cross-border commerce bridge.*
