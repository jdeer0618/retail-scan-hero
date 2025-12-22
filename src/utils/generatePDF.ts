import jsPDF from 'jspdf';
import autoTable from 'jspdf-autotable';
import { InventoryItem, ExceptionItem } from '@/types/inventory';

export function generateDiscrepancyReport(
  inventoryItems: InventoryItem[],
  exceptionItems: ExceptionItem[]
) {
  const doc = new jsPDF();
  const now = new Date();
  const dateStr = now.toLocaleDateString();
  const timeStr = now.toLocaleTimeString();

  // Title
  doc.setFontSize(20);
  doc.setFont('helvetica', 'bold');
  doc.text('Inventory Reconciliation Report', 14, 20);

  doc.setFontSize(10);
  doc.setFont('helvetica', 'normal');
  doc.text(`Generated: ${dateStr} at ${timeStr}`, 14, 28);

  let yPos = 40;

  // Discrepancies Section
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

  // Check if we need a new page
  if (yPos > 240) {
    doc.addPage();
    yPos = 20;
  }

  // Exceptions Section
  doc.setFontSize(14);
  doc.setFont('helvetica', 'bold');
  doc.text(`Exceptions - Unknown UPCs (${exceptionItems.length} items)`, 14, yPos);
  yPos += 8;

  if (exceptionItems.length > 0) {
    autoTable(doc, {
      startY: yPos,
      head: [['UPC', 'Times Scanned', 'First Scanned', 'Last Scanned']],
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
  
  doc.text(`Summary: ${discrepancies.length} discrepancies, ${exceptionItems.length} exceptions`, 14, summaryY);
  doc.text(`Total inventory items: ${inventoryItems.length}`, 14, summaryY + 5);

  // Save
  const filename = `inventory-reconciliation-${now.toISOString().split('T')[0]}.pdf`;
  doc.save(filename);
}
