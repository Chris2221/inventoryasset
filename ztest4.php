<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gate Pass PDF Generator</title>
    <!-- Tailwind CSS for styling -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- pdf-lib library for creating the PDF -->
    <script src="https://unpkg.com/pdf-lib/dist/pdf-lib.min.js"></script>
    <style>
        /* Using a modern, clean font */
        body {
            font-family: 'Inter', sans-serif;
        }

        /* Simple focus styles for better accessibility */
        input:focus,
        select:focus,
        textarea:focus {
            outline: 2px solid #3b82f6;
            outline-offset: 2px;
        }
    </style>
</head>

<body class="bg-gray-100">

    <div class="container mx-auto p-4 sm:p-8 max-w-4xl">
        <div class="bg-white p-8 rounded-2xl shadow-lg">
            <h1 class="text-3xl font-bold text-gray-800 mb-2">Gate Pass Generator</h1>
            <p class="text-gray-500 mb-8">Fill in the details below to create a custom Gate Pass PDF.</p>

            <!-- Form for Gate Pass Data -->
            <form id="gatePassForm" class="space-y-6">

                <!-- Section 1: Basic Info -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 border-t pt-6">
                    <div>
                        <label for="gpNo" class="block text-sm font-medium text-gray-700 mb-1">GP No.</label>
                        <input type="text" id="gpNo" class="w-full p-2 border border-gray-300 rounded-md shadow-sm">
                    </div>
                    <div>
                        <label for="date" class="block text-sm font-medium text-gray-700 mb-1">Date</label>
                        <input type="date" id="date" class="w-full p-2 border border-gray-300 rounded-md shadow-sm">
                    </div>
                    <div>
                        <label for="employeeName" class="block text-sm font-medium text-gray-700 mb-1">Employee Name</label>
                        <input type="text" id="employeeName" class="w-full p-2 border border-gray-300 rounded-md shadow-sm">
                    </div>
                    <div>
                        <label for="department" class="block text-sm font-medium text-gray-700 mb-1">Department</label>
                        <input type="text" id="department" class="w-full p-2 border border-gray-300 rounded-md shadow-sm">
                    </div>
                    <div>
                        <label for="contact" class="block text-sm font-medium text-gray-700 mb-1">Contact #</label>
                        <input type="text" id="contact" class="w-full p-2 border border-gray-300 rounded-md shadow-sm">
                    </div>
                    <div class="flex items-end">
                        <fieldset class="flex gap-6">
                            <legend class="block text-sm font-medium text-gray-700 mb-2">Pass Type</legend>
                            <div>
                                <input type="radio" id="one-way" name="passType" value="one-way" class="h-4 w-4 text-blue-600 border-gray-300 focus:ring-blue-500" checked>
                                <label for="one-way" class="ml-2 text-sm text-gray-700">One-Way</label>
                            </div>
                            <div>
                                <input type="radio" id="returnable" name="passType" value="returnable" class="h-4 w-4 text-blue-600 border-gray-300 focus:ring-blue-500">
                                <label for="returnable" class="ml-2 text-sm text-gray-700">Returnable</label>
                            </div>
                        </fieldset>
                    </div>
                </div>

                <!-- Section 2: Items Table -->
                <div class="border-t pt-6">
                    <h2 class="text-lg font-semibold text-gray-800 mb-4">Items</h2>
                    <div id="items-container" class="space-y-4">
                        <!-- Item rows will be injected here by JavaScript -->
                    </div>
                    <div class="mt-4 flex gap-4">
                        <button type="button" onclick="addItemRow()" class="bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-4 rounded-md shadow-sm transition duration-300">Add Item</button>
                    </div>
                </div>

                <!-- Section 3: Purpose -->
                <div class="border-t pt-6">
                    <label for="purpose" class="block text-sm font-medium text-gray-700 mb-1">Purpose</label>
                    <textarea id="purpose" rows="3" class="w-full p-2 border border-gray-300 rounded-md shadow-sm"></textarea>
                </div>

                <!-- Section 4: Approvals -->
                <div class="border-t pt-6">
                    <h2 class="text-lg font-semibold text-gray-800 mb-4">Approvals</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="itManager" class="block text-sm font-medium text-gray-700 mb-1">IT Manager</label>
                            <input type="text" id="itManager" class="w-full p-2 border border-gray-300 rounded-md shadow-sm">
                        </div>
                        <div>
                            <label for="qaManager" class="block text-sm font-medium text-gray-700 mb-1">QA Manager</label>
                            <input type="text" id="qaManager" class="w-full p-2 border border-gray-300 rounded-md shadow-sm">
                        </div>
                    </div>
                </div>


                <!-- Generate Button -->
                <div class="text-center border-t pt-6">
                    <button type="button" onclick="generatePDF()"
                        class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-8 rounded-lg shadow-lg transition duration-300 ease-in-out transform hover:scale-105 w-full sm:w-auto">
                        Generate and Preview Gate Pass PDF
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Set the date input to today's date by default
        document.getElementById('date').valueAsDate = new Date();

        // Function to add a new row for an item
        function addItemRow() {
            const container = document.getElementById('items-container');
            const itemIndex = container.children.length;
            const itemRow = document.createElement('div');
            itemRow.className = 'grid grid-cols-12 gap-4 items-center';
            itemRow.innerHTML = `
                <div class="col-span-12 sm:col-span-2">
                    <label class="text-sm font-medium text-gray-600">Qty</label>
                    <input type="text" name="quantity" class="mt-1 w-full p-2 border border-gray-300 rounded-md shadow-sm">
                </div>
                <div class="col-span-12 sm:col-span-4">
                     <label class="text-sm font-medium text-gray-600">Description</label>
                    <input type="text" name="description" class="mt-1 w-full p-2 border border-gray-300 rounded-md shadow-sm">
                </div>
                <div class="col-span-12 sm:col-span-3">
                     <label class="text-sm font-medium text-gray-600">Serial #</label>
                    <input type="text" name="serial" class="mt-1 w-full p-2 border border-gray-300 rounded-md shadow-sm">
                </div>
                <div class="col-span-12 sm:col-span-2">
                     <label class="text-sm font-medium text-gray-600">Remarks</label>
                    <input type="text" name="remarks" class="mt-1 w-full p-2 border border-gray-300 rounded-md shadow-sm">
                </div>
                <div class="col-span-12 sm:col-span-1 flex items-end justify-end pt-5">
                    <button type="button" onclick="this.parentElement.parentElement.remove()" class="text-red-500 hover:text-red-700 font-bold py-2 px-2 rounded-md transition duration-300">
                        &times;
                    </button>
                </div>
            `;
            container.appendChild(itemRow);
        }

        // Add one item row by default when the page loads
        window.onload = () => {
            addItemRow();
        };


        async function generatePDF() {
            // --- 1. GATHER DATA FROM THE FORM ---
            const gpNo = document.getElementById('gpNo').value;
            const dateInput = document.getElementById('date').value;
            const employeeName = document.getElementById('employeeName').value;
            const department = document.getElementById('department').value;
            const contact = document.getElementById('contact').value;
            const passType = document.querySelector('input[name="passType"]:checked').value;
            const purpose = document.getElementById('purpose').value;
            const itManager = document.getElementById('itManager').value;
            const qaManager = document.getElementById('qaManager').value;

            const date = dateInput ? new Date(dateInput).toLocaleDateString('en-US') : '';

            const items = [{
                    quantity: '10',
                    description: 'Laptop',
                    serial: 'SN123456',
                    remarks: 'New units'
                },
                {
                    quantity: '5',
                    description: 'Monitor',
                    serial: 'SN654321',
                    remarks: 'For office use'
                },
                {
                    quantity: '3',
                    description: 'Keyboard',
                    serial: 'SN987654',
                    remarks: 'Mechanical'
                },
                {
                    quantity: '7',
                    description: 'Mouse',
                    serial: 'SN192837',
                    remarks: 'Wireless'
                },
                {
                    quantity: '2',
                    description: 'Printer',
                    serial: 'SN564738',
                    remarks: 'Color laser'
                }
            ];


            // --- 2. SETUP PDF DOCUMENT ---
            const {
                PDFDocument,
                rgb,
                StandardFonts
            } = PDFLib;
            const pdfDoc = await PDFDocument.create();
            let page = pdfDoc.addPage([612, 936]);

            const font = await pdfDoc.embedFont(StandardFonts.Helvetica);
            const boldFont = await pdfDoc.embedFont(StandardFonts.HelveticaBold);
            const baseFontSize = 10;

            const MARGIN_LEFT = 50;
            const MARGIN_TOP = 50;
            const MARGIN_BOTTOM = 50;
            const CONTENT_WIDTH = page.getWidth() - MARGIN_LEFT * 2;
            let y = page.getHeight() - MARGIN_TOP;

            // --- 3. PDF DRAWING HELPERS ---
            const drawText = (text, x, yPos, options = {}) => {
                page.drawText(text, {
                    x,
                    y: yPos,
                    font: options.font || font,
                    size: options.size || baseFontSize,
                    color: options.color || rgb(0, 0, 0),
                    lineHeight: (options.size || baseFontSize) + 4,
                    maxWidth: options.maxWidth
                });
            };
            const drawCenteredText = (text, yPos, options = {}) => {
                const currentFont = options.font || font;
                const currentSize = options.size || baseFontSize;
                const textWidth = currentFont.widthOfTextAtSize(text, currentSize);
                const x = MARGIN_LEFT + (CONTENT_WIDTH - textWidth) / 2;
                drawText(text, x, yPos, options);
            };
            const drawRectangle = (x, yPos, width, height, options = {}) => {
                page.drawRectangle({
                    x,
                    y: yPos,
                    width,
                    height,
                    borderColor: options.borderColor || rgb(0, 0, 0),
                    borderWidth: options.borderWidth || 1,
                    color: options.color,
                });
            };
            const drawLine = (x1, y1, x2, y2, options = {}) => {
                page.drawLine({
                    start: {
                        x: x1,
                        y: y1
                    },
                    end: {
                        x: x2,
                        y: y2
                    },
                    thickness: options.thickness || 1,
                    color: options.color || rgb(0, 0, 0),
                });
            };

            // --- Reusable Drawing Functions ---
            const drawPageHeader = () => {
                y = page.getHeight() - MARGIN_TOP;
                drawCenteredText("GATE PASS", y, {
                    font: boldFont,
                    size: 18
                });
                y -= 20;
                drawCenteredText("(I.T OPERATIONS EQUIPMENT)", y, {
                    size: 11
                });
                y -= 25;

                const checkboxY = y + 2;
                drawRectangle(MARGIN_LEFT + 120, checkboxY, 12, 12);
                drawText("ONE-WAY", MARGIN_LEFT + 140, checkboxY);
                if (passType === 'one-way') {
                    drawText('X', MARGIN_LEFT + 122, checkboxY + 1, {
                        font: boldFont,
                        size: 10
                    });
                }
                drawRectangle(MARGIN_LEFT + 280, checkboxY, 12, 12);
                drawText("RETURNABLE", MARGIN_LEFT + 300, checkboxY);
                if (passType === 'returnable') {
                    drawText('X', MARGIN_LEFT + 282, checkboxY + 1, {
                        font: boldFont,
                        size: 10
                    });
                }
                y -= 30;

                drawText("GP No:", MARGIN_LEFT, y);
                drawLine(MARGIN_LEFT + 50, y - 2, MARGIN_LEFT + 250, y - 2);
                drawText(gpNo, MARGIN_LEFT + 52, y);
                drawText("Date:", MARGIN_LEFT + 300, y);
                drawLine(MARGIN_LEFT + 340, y - 2, CONTENT_WIDTH + MARGIN_LEFT, y - 2);
                drawText(date, MARGIN_LEFT + 342, y);
                y -= 25;

                drawText("Employee Name:", MARGIN_LEFT, y);
                drawLine(MARGIN_LEFT + 95, y - 2, MARGIN_LEFT + 250, y - 2);
                drawText(employeeName, MARGIN_LEFT + 97, y);
                drawText("Department:", MARGIN_LEFT + 300, y);
                drawLine(MARGIN_LEFT + 370, y - 2, CONTENT_WIDTH + MARGIN_LEFT, y - 2);
                drawText(department, MARGIN_LEFT + 372, y);
                y -= 25;

                drawText("Contact #:", MARGIN_LEFT, y);
                drawLine(MARGIN_LEFT + 65, y - 2, MARGIN_LEFT + 250, y - 2);
                drawText(contact, MARGIN_LEFT + 67, y);
                y -= 30;
            };

            const tableColumns = [{
                    x: MARGIN_LEFT,
                    width: 70,
                    title: 'QUANTITY'
                },
                {
                    x: MARGIN_LEFT + 70,
                    width: 180,
                    title: 'DESCRIPTION'
                },
                {
                    x: MARGIN_LEFT + 250,
                    width: 130,
                    title: 'SERIAL #'
                },
                {
                    x: MARGIN_LEFT + 380,
                    width: 132,
                    title: 'REMARKS'
                }
            ];
            const drawTableHeader = () => {
                drawRectangle(MARGIN_LEFT, y - 20, CONTENT_WIDTH, 20, {
                    color: rgb(0.9, 0.9, 0.9)
                });
                tableColumns.forEach(col => {
                    drawText(col.title, col.x + 5, y - 14, {
                        font: boldFont
                    });
                });
                y -= 20;
                return y + 20; // Return the top Y of the table
            };

            const drawFooter = () => {
                const FOOTER_HEIGHT = 450;
                if (y < MARGIN_BOTTOM + FOOTER_HEIGHT) {
                    page = pdfDoc.addPage([612, 936]);
                    drawPageHeader();
                }

                y -= 10;
                const purposeHeight = 60;
                drawRectangle(MARGIN_LEFT, y - purposeHeight, CONTENT_WIDTH, purposeHeight);
                drawText("PURPOSE:", MARGIN_LEFT + 5, y - 15, {
                    font: boldFont
                });
                drawText(purpose, MARGIN_LEFT + 5, y - 30, {
                    maxWidth: CONTENT_WIDTH - 10
                });
                y -= (purposeHeight + 10);

                drawText("Note: (Indicate Accountability / Attach copy of MR)", MARGIN_LEFT, y);
                y -= 30;

                drawText("Requested By:", MARGIN_LEFT, y, {
                    font: boldFont
                });
                y -= 40;
                const signatureLineWidth = 250;
                drawLine(MARGIN_LEFT, y, MARGIN_LEFT + signatureLineWidth, y);
                if (employeeName) {
                    const employeeNameWidth = font.widthOfTextAtSize(employeeName, baseFontSize);
                    const centeredEmployeeNameX = MARGIN_LEFT + (signatureLineWidth - employeeNameWidth) / 2;
                    drawText(employeeName, centeredEmployeeNameX, y + 5);
                }
                const signatureText = "Signature Over Printed Name of Employee";
                const signatureTextWidth = font.widthOfTextAtSize(signatureText, baseFontSize);
                const centeredSignatureTextX = MARGIN_LEFT + (signatureLineWidth - signatureTextWidth) / 2;
                drawText(signatureText, centeredSignatureTextX, y - 12);
                y -= 40;

                drawText("RECOMMENDING APPROVAL:", MARGIN_LEFT, y, {
                    font: boldFont
                });
                y -= 20;
                drawText("I.T Department", MARGIN_LEFT, y);
                y -= 40;

                const managerY = y;
                const managerLineWidth = 200;
                drawLine(MARGIN_LEFT, managerY, MARGIN_LEFT + managerLineWidth, managerY);
                if (itManager) {
                    const itManagerWidth = font.widthOfTextAtSize(itManager, baseFontSize);
                    const centeredItManagerX = MARGIN_LEFT + (managerLineWidth - itManagerWidth) / 2;
                    drawText(itManager, centeredItManagerX, managerY + 5);
                }
                drawText("IT MANAGER", MARGIN_LEFT + 60, managerY - 12);
                const qaManagerX = MARGIN_LEFT + 312;
                drawLine(qaManagerX, managerY, qaManagerX + managerLineWidth, managerY);
                if (qaManager) {
                    const qaManagerWidth = font.widthOfTextAtSize(qaManager, baseFontSize);
                    const centeredQaManagerX = qaManagerX + (managerLineWidth - qaManagerWidth) / 2;
                    drawText(qaManager, centeredQaManagerX, managerY + 5);
                }
                drawText("QA MANAGER", MARGIN_LEFT + 362, managerY - 12);
                y -= 40;

                const notesHeight = 50;
                drawRectangle(MARGIN_LEFT, y - notesHeight, CONTENT_WIDTH, notesHeight);
                drawText("Notes/Comments:", MARGIN_LEFT + 5, y - 15);
                y -= (notesHeight + 20);

                drawText("FINAL APPROVAL:", MARGIN_LEFT, y, {
                    font: boldFont
                });
                y -= 35;
                const finalApprovalY = y;
                drawLine(MARGIN_LEFT, finalApprovalY, MARGIN_LEFT + 200, finalApprovalY);
                drawText("Paul Abenes", MARGIN_LEFT + 60, finalApprovalY - 12);
                drawText("AND/OR", MARGIN_LEFT + 240, finalApprovalY);
                drawLine(MARGIN_LEFT + 312, finalApprovalY, CONTENT_WIDTH + MARGIN_LEFT, finalApprovalY);
                drawText("CSM", MARGIN_LEFT + 392, finalApprovalY - 12);
                y -= 40;

                const clearanceHeight = 150;
                const clearanceTopY = y;
                drawRectangle(MARGIN_LEFT, clearanceTopY - clearanceHeight, CONTENT_WIDTH, clearanceHeight);
                drawText("CLEARANCE (To be filled up by Guard on Duty)", MARGIN_LEFT + 5, clearanceTopY - 15, {
                    font: boldFont
                });
                let innerY = clearanceTopY - 35;
                drawText("Date out:", MARGIN_LEFT + 10, innerY);
                drawLine(MARGIN_LEFT + 65, innerY - 2, MARGIN_LEFT + 200, innerY - 2);
                drawText("Time out:", MARGIN_LEFT + 250, innerY);
                drawLine(MARGIN_LEFT + 305, innerY - 2, MARGIN_LEFT + 440, innerY - 2);
                innerY -= 25;
                drawText("Guard on Duty (OUT):", MARGIN_LEFT + 10, innerY);
                drawLine(MARGIN_LEFT + 130, innerY - 2, CONTENT_WIDTH + MARGIN_LEFT - 10, innerY - 2);
                drawText("Signature over Printed Name", MARGIN_LEFT + 250, innerY - 12);
                innerY -= 25;
                drawLine(MARGIN_LEFT, innerY, CONTENT_WIDTH + MARGIN_LEFT, innerY, {
                    thickness: 0.5
                });
                innerY -= 20;
                drawText("Date in:", MARGIN_LEFT + 10, innerY);
                drawLine(MARGIN_LEFT + 60, innerY - 2, MARGIN_LEFT + 200, innerY - 2);
                drawText("Time in:", MARGIN_LEFT + 250, innerY);
                drawLine(MARGIN_LEFT + 300, innerY - 2, MARGIN_LEFT + 440, innerY - 2);
                innerY -= 25;
                drawText("Guard on Duty (IN):", MARGIN_LEFT + 10, innerY);
                drawLine(MARGIN_LEFT + 125, innerY - 2, CONTENT_WIDTH + MARGIN_LEFT - 10, innerY - 2);
                drawText("Signature over Printed Name", MARGIN_LEFT + 250, innerY - 12);
            };

            // --- 4. DRAW PDF CONTENT ---
            drawPageHeader();
            let tableTopY = drawTableHeader();
            const rowHeight = 20;

            for (const item of items) {
                if (y - rowHeight <= MARGIN_BOTTOM) { // Check if new row fits
                    drawRectangle(MARGIN_LEFT, y, CONTENT_WIDTH, tableTopY - y); // Close current table
                    tableColumns.forEach(col => {
                        if (col.x > MARGIN_LEFT) drawLine(col.x, tableTopY, col.x, y);
                    });

                    page = pdfDoc.addPage([612, 936]);
                    drawPageHeader();
                    tableTopY = drawTableHeader();
                }

                drawText(item.quantity || '', tableColumns[0].x + 5, y - 14);
                drawText(item.description || '', tableColumns[1].x + 5, y - 14);
                drawText(item.serial || '', tableColumns[2].x + 5, y - 14);
                drawText(item.remarks || '', tableColumns[3].x + 5, y - 14);
                y -= rowHeight;
            }

            // Finalize the last table
            drawRectangle(MARGIN_LEFT, y, CONTENT_WIDTH, tableTopY - y);
            tableColumns.forEach(col => {
                if (col.x > MARGIN_LEFT) drawLine(col.x, tableTopY, col.x, y);
            });

            drawFooter();

            // --- 5. SAVE AND OPEN PDF ---
            const pdfBytes = await pdfDoc.save();
            const blob = new Blob([pdfBytes], {
                type: "application/pdf"
            });
            const url = URL.createObjectURL(blob);
            window.open(url, '_blank');
        }
    </script>
</body>

</html>