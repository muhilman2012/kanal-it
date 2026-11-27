"""Render filtered Zoom request history from JSON stdin to PDF stdout."""
import io
import json
import sys
from collections import Counter
from datetime import datetime
from xml.sax.saxutils import escape

from reportlab.lib import colors
from reportlab.lib.enums import TA_LEFT
from reportlab.lib.pagesizes import A4, landscape
from reportlab.lib.styles import ParagraphStyle
from reportlab.platypus import SimpleDocTemplate, Table, TableStyle, Paragraph, Spacer

data = json.load(sys.stdin)
buffer = io.BytesIO()
doc = SimpleDocTemplate(
    buffer, pagesize=landscape(A4), leftMargin=38, rightMargin=38,
    topMargin=43, bottomMargin=43, title="Riwayat Permintaan Zoom",
)
navy = colors.HexColor("#17333b")
green = colors.HexColor("#146656")
muted = colors.HexColor("#52676c")

def paragraph(value, size=8, bold=False, color=navy):
    style = ParagraphStyle(
        "cell", fontName="Helvetica-Bold" if bold else "Helvetica",
        fontSize=size, leading=size + 3, textColor=color, alignment=TA_LEFT,
    )
    return Paragraph(escape(str(value or "—")).replace("\n", "<br/>"), style)

rows = data["rows"]
story = [
    paragraph("RIWAYAT PERMINTAAN ZOOM", 15, True),
    Spacer(1, 4),
    paragraph("Periode kegiatan: " + data["period"], 10, True, green),
    paragraph("Dibuat: " + data["generated"] + "  |  Jumlah permintaan: " + str(len(rows)), 8, False, muted),
    Spacer(1, 15),
]
if rows:
    counts = Counter(row["status"] for row in rows)
    story.extend([
        paragraph("Status: " + "  •  ".join(f"{key}: {value}" for key, value in sorted(counts.items())), 8, False, muted),
        Spacer(1, 10),
    ])
    columns = ["Kode", "Tanggal / waktu WIB", "Judul kegiatan", "Unit / PIC", "Peserta", "Akun Zoom", "Status"]
    table_rows = [[paragraph(label, 8, True, colors.white) for label in columns]]
    for row in rows:
        start = datetime.fromisoformat(row["starts_at"])
        end = datetime.fromisoformat(row["ends_at"])
        schedule = start.strftime("%d/%m/%Y %H:%M") + "–" + end.strftime("%H:%M")
        table_rows.append([
            paragraph(row.get("code") or row["id"], 7, True),
            paragraph(schedule, 7),
            paragraph(row["agenda"], 7),
            paragraph(row["unit"] + "\n" + row["requester_name"], 7),
            paragraph(row["participants"], 7),
            paragraph(row.get("account_name"), 7),
            paragraph(row["status"].capitalize(), 7, True),
        ])
    table = Table(table_rows, colWidths=[100, 98, 155, 135, 43, 92, 67], repeatRows=1, hAlign="LEFT")
    table.setStyle(TableStyle([
        ("BACKGROUND", (0, 0), (-1, 0), green),
        ("ROWBACKGROUNDS", (0, 1), (-1, -1), [colors.white, colors.HexColor("#f0f5f3")]),
        ("VALIGN", (0, 0), (-1, -1), "TOP"),
        ("LINEBELOW", (0, -1), (-1, -1), 0.5, colors.HexColor("#d4dfe0")),
        ("LEFTPADDING", (0, 0), (-1, -1), 7),
        ("RIGHTPADDING", (0, 0), (-1, -1), 7),
        ("TOPPADDING", (0, 0), (-1, -1), 8),
        ("BOTTOMPADDING", (0, 0), (-1, -1), 8),
    ]))
    story.append(table)
else:
    story.append(paragraph("Tidak ada permintaan Zoom untuk periode ini.", 10))

def footer(canvas, document):
    canvas.setStrokeColor(colors.HexColor("#d4dfe0"))
    canvas.line(38, 33, landscape(A4)[0] - 38, 33)
    canvas.setFont("Helvetica", 8)
    canvas.setFillColor(muted)
    canvas.drawString(38, 20, "Kanal IT • Riwayat Permintaan Zoom")
    canvas.drawRightString(landscape(A4)[0] - 38, 20, f"Halaman {document.page}")

doc.build(story, onFirstPage=footer, onLaterPages=footer)
sys.stdout.buffer.write(buffer.getvalue())
