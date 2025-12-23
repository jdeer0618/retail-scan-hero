import jsPDF from 'jspdf';
import autoTable from 'jspdf-autotable';
import { InventoryItem, ExceptionItem, SerializedItem, InventoryMode } from '@/types/inventory';

export function generateDiscrepancyReport(
  inventoryItems: InventoryItem[],
  serializedItems: SerializedItem[],
  exceptionItems: ExceptionItem[],
  mode: InventoryMode
) {
  const doc = new jsPDF();
  const now = new Date();
  const dateStr = now.toLocaleDateString();
  const timeStr = now.toLocaleTimeString();
  const isSerialMode = mode === 'serialized';

  // Title
  doc.setFontSize(20);
  doc.setFont('helvetica', 'bold');
  doc.text(isSerialMode ? 'Serialized Inventory Report' : 'Inventory Reconciliation Report', 14, 20);

  doc.setFontSize(10);
  doc.setFont('helvetica', 'normal');
  doc.text(`Generated: ${dateStr} at ${timeStr}`, 14, 28);
  doc.text(`Mode: ${isSerialMode ? 'Serialized Inventory' : 'UPC Inventory'}`, 14, 34);

  let yPos = 46;

  if (isSerialMode) {
    // Serialized mode - show not scanned items
    const notScanned = serializedItems.filter(item => !item.isScanned);
    const scanned = serializedItems.filter(item => item.isScanned);

    // Summary section
    doc.setFontSize(12);
    doc.setFont('helvetica', 'bold');
    doc.text('Summary', 14, yPos);
    yPos += 8;
    
    doc.setFontSize(10);
    doc.setFont('helvetica', 'normal');
    doc.text(`Total Items: ${serializedItems.length}`, 14, yPos);
    doc.text(`Scanned: ${scanned.length}`, 70, yPos);
    doc.text(`Not Scanned: ${notScanned.length}`, 120, yPos);
    doc.text(`Exceptions: ${exceptionItems.length}`, 170, yPos);
    yPos += 12;

    // Not Scanned Section
    doc.setFontSize(14);
    doc.setFont('helvetica', 'bold');
    doc.text(`Not Scanned (${notScanned.length} items)`, 14, yPos);
    yPos += 8;

    if (notScanned.length > 0) {
      autoTable(doc, {
        startY: yPos,
        head: [['Serial Number', 'Manufacturer', 'Model', 'Caliber']],
        body: notScanned.map((item) => [
          item.serialNumber,
          item.manufacturer,
          item.model.substring(0, 25) + (item.model.length > 25 ? '...' : ''),
          item.caliber,
        ]),
        theme: 'grid',
        styles: { fontSize: 8, cellPadding: 2 },
        headStyles: { fillColor: [245, 158, 11], textColor: 255 },
        alternateRowStyles: { fillColor: [255, 251, 235] },
        columnStyles: {
          0: { cellWidth: 45 },
          1: { cellWidth: 45 },
          2: { cellWidth: 55 },
          3: { cellWidth: 35 },
        },
      });

      yPos = (doc as any).lastAutoTable.finalY + 15;
    } else {
      doc.setFontSize(10);
      doc.setFont('helvetica', 'italic');
      doc.text('All items have been scanned.', 14, yPos);
      yPos += 15;
    }

    // Check if we need a new page
    if (yPos > 240) {
      doc.addPage();
      yPos = 20;
    }

    // Scanned Section
    doc.setFontSize(14);
    doc.setFont('helvetica', 'bold');
    doc.text(`Scanned (${scanned.length} items)`, 14, yPos);
    yPos += 8;

    if (scanned.length > 0) {
      autoTable(doc, {
        startY: yPos,
        head: [['Serial Number', 'Manufacturer', 'Model', 'Scanned At']],
        body: scanned.map((item) => [
          item.serialNumber,
          item.manufacturer,
          item.model.substring(0, 25) + (item.model.length > 25 ? '...' : ''),
          item.scannedAt ? new Date(item.scannedAt).toLocaleString() : '-',
        ]),
        theme: 'grid',
        styles: { fontSize: 8, cellPadding: 2 },
        headStyles: { fillColor: [34, 197, 94], textColor: 255 },
        alternateRowStyles: { fillColor: [240, 253, 244] },
        columnStyles: {
          0: { cellWidth: 45 },
          1: { cellWidth: 45 },
          2: { cellWidth: 50 },
          3: { cellWidth: 40 },
        },
      });

      yPos = (doc as any).lastAutoTable.finalY + 15;
    } else {
      doc.setFontSize(10);
      doc.setFont('helvetica', 'italic');
      doc.text('No items have been scanned yet.', 14, yPos);
      yPos += 15;
    }

  } else {
    // UPC mode - show discrepancies
    const discrepancies = inventoryItems.filter(
      (item) => item.scannedQuantity > 0 && item.scannedQuantity !== item.expectedQuantity
    );

    doc.setFontSize(14);
    doc.setFont('helvetica', 'bold');
    doc.text(`Discrepancies (${discrepancies.length} items)`, 14, yPos);
    yPos += 8;

    if (discrepancies.length > 0) {
      autoTable(doc, {
        startY: yPos,
        head: [['UPC', 'Item Name', 'Expected', 'Scanned', 'Difference']],
        body: discrepancies.map((item) => [
          item.upc,
          item.itemName.substring(0, 40) + (item.itemName.length > 40 ? '...' : ''),
          item.expectedQuantity.toString(),
          item.scannedQuantity.toString(),
          (item.scannedQuantity - item.expectedQuantity).toString(),
        ]),
        theme: 'grid',
        styles: { fontSize: 8, cellPadding: 2 },
        headStyles: { fillColor: [59, 130, 246], textColor: 255 },
        alternateRowStyles: { fillColor: [245, 245, 245] },
        columnStyles: {
          0: { cellWidth: 35 },
          1: { cellWidth: 70 },
          2: { cellWidth: 25, halign: 'center' },
          3: { cellWidth: 25, halign: 'center' },
          4: { cellWidth: 25, halign: 'center' },
        },
      });

      yPos = (doc as any).lastAutoTable.finalY + 15;
    } else {
      doc.setFontSize(10);
      doc.setFont('helvetica', 'italic');
      doc.text('No discrepancies found.', 14, yPos);
      yPos += 15;
    }
  }

  // Check if we need a new page
  if (yPos > 240) {
    doc.addPage();
    yPos = 20;
  }

  // Exceptions Section
  doc.setFontSize(14);
  doc.setFont('helvetica', 'bold');
  doc.text(`Exceptions - Unknown ${isSerialMode ? 'Serials' : 'UPCs'} (${exceptionItems.length} items)`, 14, yPos);
  yPos += 8;

  if (exceptionItems.length > 0) {
    autoTable(doc, {
      startY: yPos,
      head: [[isSerialMode ? 'Serial Number' : 'UPC', 'Times Scanned', 'First Scanned', 'Last Scanned']],
      body: exceptionItems.map((item) => [
        item.upc,
        item.scannedQuantity.toString(),
        item.firstScanned
          ? new Date(item.firstScanned).toLocaleString()
          : '-',
        item.lastScanned
          ? new Date(item.lastScanned).toLocaleString()
          : '-',
      ]),
      theme: 'grid',
      styles: { fontSize: 8, cellPadding: 2 },
      headStyles: { fillColor: [239, 68, 68], textColor: 255 },
      alternateRowStyles: { fillColor: [255, 245, 245] },
      columnStyles: {
        0: { cellWidth: 50 },
        1: { cellWidth: 30, halign: 'center' },
        2: { cellWidth: 50 },
        3: { cellWidth: 50 },
      },
    });
  } else {
    doc.setFontSize(10);
    doc.setFont('helvetica', 'italic');
    doc.text('No exceptions found.', 14, yPos);
  }

  // Summary on last page
  const pageCount = doc.getNumberOfPages();
  doc.setPage(pageCount);
  
  const summaryY = doc.internal.pageSize.height - 30;
  doc.setFontSize(9);
  doc.setFont('helvetica', 'normal');
  doc.setDrawColor(200, 200, 200);
  doc.line(14, summaryY - 5, 196, summaryY - 5);
  
  if (isSerialMode) {
    const notScanned = serializedItems.filter(item => !item.isScanned).length;
    const scanned = serializedItems.filter(item => item.isScanned).length;
    doc.text(`Summary: ${scanned} scanned, ${notScanned} not scanned, ${exceptionItems.length} exceptions`, 14, summaryY);
    doc.text(`Total serialized items: ${serializedItems.length}`, 14, summaryY + 5);
  } else {
    const discrepancies = inventoryItems.filter(
      (item) => item.scannedQuantity > 0 && item.scannedQuantity !== item.expectedQuantity
    );
    doc.text(`Summary: ${discrepancies.length} discrepancies, ${exceptionItems.length} exceptions`, 14, summaryY);
    doc.text(`Total inventory items: ${inventoryItems.length}`, 14, summaryY + 5);
  }

  // Save
  const filename = `${isSerialMode ? 'serial' : 'inventory'}-reconciliation-${now.toISOString().split('T')[0]}.pdf`;
  doc.save(filename);
}
