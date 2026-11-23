"""Render one employee's monthly report. JSON input and PDF output use pipes."""
import io
import json
import re
import sys
from collections import Counter
from xml.sax.saxutils import escape

from reportlab.lib import colors
from reportlab.lib.enums import TA_CENTER
from reportlab.lib.pagesizes import A4, landscape
from reportlab.lib.styles import ParagraphStyle
from reportlab.platypus import SimpleDocTemplate, Table, TableStyle, Paragraph, Spacer, KeepTogether

data = json.load(sys.stdin)
output = io.BytesIO()
doc = SimpleDocTemplate(output, pagesize=landscape(A4), leftMargin=76, rightMargin=76,
                        topMargin=49, bottomMargin=45, title=data["title"])
widths = [50, 70, 17, 138, 110, 120, 75, 96]
def para(value="", font="Helvetica", size=8, align=TA_CENTER):
    style = ParagraphStyle("cell", fontName=font, fontSize=size, leading=size+2, alignment=align)
    return Paragraph(escape(str(value or "")).replace("\n","<br/>"), style)

def label(value):
    return para(value,"Times-Bold",8)

story = [para(data["title"],"Times-Bold",12), Spacer(1,4), label("LAPORAN KINERJA PEGAWAI OUTSOURCING TEKNOLOGI INFORMASI"),
         label("BIRO TATA USAHA DAN SUMBER DAYA MANUSIA"), Spacer(1,8)]
identity = [[para(k,"Times-Bold",8,0),para(":   "+v,"Times-Bold",8,0)] for k,v in
            [("BULAN",data["month"]),("NAMA LENGKAP",data["name"]),("POSISI / JABATAN",data["position"])]]
story.extend([Table(identity,[124,sum(widths)-124],style=[("VALIGN",(0,0),(-1,-1),"TOP"),
    ("TOPPADDING",(0,0),(-1,-1),0),("BOTTOMPADDING",(0,0),(-1,-1),0)]),Spacer(1,10)])
headers = ["TANGGAL","STATUS\nKEHADIRAN","URAIAN PEKERJAAN","","STATUS\n[Selesai|Dalam Proses]",
           "KENDALA\n(Jika Ada)","INISIATIF/\nPERMINTAAN","KETERANGAN"]
rows = [[label(h) for h in headers]]
counts = Counter()
previous = None
for record in data["rows"]:
    date = record["report_date"]
    attendance = record.get("attendance")
    # Numbered or bulleted activities become separate rows. Ordinary wrapped prose stays together.
    activities = [v.strip() for v in re.split(r"(?m)^\s*(?:\d+[.)]|[-•])\s+",record["body"]) if v.strip()]
    for activity in activities or [record["body"]]:
        counts[date] += 1
        cells = ["/".join(reversed(date.split("-"))) if counts[date]==1 else "",
                 attendance if previous != (date,attendance) else "", counts[date] if activity else "",activity,
                 record.get("progress"),record.get("obstacle"),record.get("initiative"),record.get("note")]
        previous = (date,attendance)
        # Split unusually tall cells before pagination, including multiline notes.
        parts = []
        for value,width in zip(cells,widths):
            pending = [para(value)]
            pieces = []
            while pending:
                p = pending.pop(0)
                if p.wrap(width-8,10000)[1] <= 250:
                    pieces.append(p)
                else:
                    a,b = p.split(width-8,240)
                    pieces.append(a)
                    pending.insert(0,b)
            parts.append(pieces)
        for i in range(max(map(len,parts))):
            rows.append([cell[i] if i<len(cell) else "" for cell in parts])
table = Table(rows,colWidths=widths,repeatRows=1,style=[
    ("SPAN",(2,0),(3,0)),("BACKGROUND",(0,0),(-1,0),colors.HexColor("#92D571")),
    ("GRID",(0,0),(-1,-1),0.55,colors.black),("VALIGN",(0,0),(-1,-1),"MIDDLE"),
    ("LEFTPADDING",(0,0),(-1,-1),4),("RIGHTPADDING",(0,0),(-1,-1),4),
    ("TOPPADDING",(0,0),(-1,-1),5),("BOTTOMPADDING",(0,0),(-1,-1),5)])
story.append(table)
sign = Table([["",para("Jakarta, "+data["signed"],"Times-Roman")],
              [para("Mengetahui,","Times-Roman"),para("Penyusun Laporan,","Times-Roman")],
              ["",""],[label("("+"\u00a0"*38+")"),label("("+data["name"]+")")]],
             [sum(widths)/2]*2,rowHeights=[12,12,54,None],style=[("ALIGN",(0,0),(-1,-1),"CENTER")])
story.append(KeepTogether([Spacer(1,22),sign,Spacer(1,20),para("Catatan:","Times-Bold",8,0),
                           para("Mohon diisi setiap hari sesuai dengan jumlah hari kerja setiap bulan.","Times-Roman",8,0)]))
def footer(canvas, doc):
    canvas.setFont("Helvetica",8)
    canvas.drawRightString(820,23,str(doc.page)+"/")
doc.build(story,onFirstPage=footer,onLaterPages=footer)
sys.stdout.buffer.write(output.getvalue())
