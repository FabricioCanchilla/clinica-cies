package bo.cies.quarkus.comprobante;

import java.awt.Color;
import java.io.ByteArrayOutputStream;
import java.math.BigDecimal;
import java.time.LocalDate;
import java.time.LocalDateTime;
import java.time.format.DateTimeFormatter;
import java.util.List;

import com.lowagie.text.Document;
import com.lowagie.text.Element;
import com.lowagie.text.Font;
import com.lowagie.text.FontFactory;
import com.lowagie.text.Image;
import com.lowagie.text.PageSize;
import com.lowagie.text.Paragraph;
import com.lowagie.text.Phrase;
import com.lowagie.text.pdf.PdfPCell;
import com.lowagie.text.pdf.PdfPTable;
import com.lowagie.text.pdf.PdfWriter;

public final class ComprobantePdf {

    public record Linea(String codSrv, int cantidad, BigDecimal precio, int descuento, BigDecimal subtotal) {
    }

    private static final DateTimeFormatter FH = DateTimeFormatter.ofPattern("dd/MM/yyyy HH:mm");
    private static final DateTimeFormatter F = DateTimeFormatter.ofPattern("dd/MM/yyyy");

    private static final Font TITULO = FontFactory.getFont(FontFactory.HELVETICA_BOLD, 18);
    private static final Font SUBT = FontFactory.getFont(FontFactory.HELVETICA_BOLD, 12);
    private static final Font NORMAL = FontFactory.getFont(FontFactory.HELVETICA, 10);
    private static final Font NEGRITA = FontFactory.getFont(FontFactory.HELVETICA_BOLD, 10);
    private static final Font PIE = FontFactory.getFont(FontFactory.HELVETICA_OBLIQUE, 8, Color.GRAY);
    private static final Font ANULADO = FontFactory.getFont(FontFactory.HELVETICA_BOLD, 16, Color.RED);

    private ComprobantePdf() {
    }

    /** Cabecera con el logo institucional CIES; si no se encuentra el recurso se usa el texto. */
    private static void cabecera(Document doc) throws Exception {
        try (var in = ComprobantePdf.class.getResourceAsStream("/logo-cies.png")) {
            if (in != null) {
                Image logo = Image.getInstance(in.readAllBytes());
                logo.scaleToFit(160, 56);
                logo.setAlignment(Element.ALIGN_LEFT);
                doc.add(logo);
                return;
            }
        } catch (Exception e) {
            // sin logo: se usa el nombre en texto
        }
        doc.add(new Paragraph("Clínica CIES", TITULO));
    }

    public static String fecha(LocalDate d) {
        return d == null ? "" : d.format(F);
    }

    private static String bs(BigDecimal v) {
        return "Bs " + v.setScale(2, java.math.RoundingMode.HALF_UP).toPlainString();
    }

    // ---------- Comprobante de pago ----------

    public static byte[] pago(String nro, LocalDateTime fec, String estado, String paciente, String ci,
                              String codCit, String cita, String emisor, List<Linea> lineas, BigDecimal total) {
        try {
            ByteArrayOutputStream out = new ByteArrayOutputStream();
            Document doc = new Document(PageSize.A4, 40, 40, 40, 40);
            PdfWriter.getInstance(doc, out);
            doc.open();

            cabecera(doc);
            doc.add(new Paragraph("Comprobante de pago N° " + nro, SUBT));
            if ("Anulado".equals(estado)) {
                doc.add(new Paragraph("COMPROBANTE ANULADO", ANULADO));
            }
            doc.add(new Paragraph(" "));
            doc.add(new Paragraph("Fecha de emisión: " + fec.format(FH), NORMAL));
            doc.add(new Paragraph("Paciente: " + paciente + "  (CI " + ci + ")", NORMAL));
            doc.add(new Paragraph("Cita: " + codCit + " — " + cita, NORMAL));
            doc.add(new Paragraph("Emitido por: " + emisor, NORMAL));
            doc.add(new Paragraph(" "));

            PdfPTable t = new PdfPTable(new float[] {4.5f, 1.2f, 2f, 1.6f, 2f});
            t.setWidthPercentage(100);
            for (String h : new String[] {"Servicio", "Cant.", "P. unitario", "Desc. %", "Subtotal"}) {
                PdfPCell cell = new PdfPCell(new Phrase(h, NEGRITA));
                cell.setBackgroundColor(new Color(230, 230, 230));
                cell.setPadding(5);
                t.addCell(cell);
            }
            for (Linea l : lineas) {
                t.addCell(celda(l.codSrv(), Element.ALIGN_LEFT));
                t.addCell(celda(String.valueOf(l.cantidad()), Element.ALIGN_CENTER));
                t.addCell(celda(bs(l.precio()), Element.ALIGN_RIGHT));
                t.addCell(celda(String.valueOf(l.descuento()), Element.ALIGN_CENTER));
                t.addCell(celda(bs(l.subtotal()), Element.ALIGN_RIGHT));
            }
            doc.add(t);

            Paragraph tot = new Paragraph("TOTAL: " + bs(total), SUBT);
            tot.setAlignment(Element.ALIGN_RIGHT);
            tot.setSpacingBefore(10);
            doc.add(tot);

            Paragraph pie = new Paragraph(
                    "Este comprobante es un respaldo interno de pago y no reemplaza a la factura.", PIE);
            pie.setSpacingBefore(30);
            doc.add(pie);

            doc.close();
            return out.toByteArray();
        } catch (Exception e) {
            throw new ComprobanteException(500, "No se pudo generar el PDF");
        }
    }

    // ---------- Comprobante de entrega de insumos ----------

    public static byte[] entrega(String nro, LocalDateTime fec, String estado, String medicamento, String dosis,
                                 int cantidad, String lote, String vence, String paciente, String entregante,
                                 String ciEntregante, String enfermero) {
        try {
            ByteArrayOutputStream out = new ByteArrayOutputStream();
            Document doc = new Document(PageSize.A4, 40, 40, 40, 40);
            PdfWriter.getInstance(doc, out);
            doc.open();

            cabecera(doc);
            doc.add(new Paragraph("Comprobante de entrega de insumos N° " + nro, SUBT));
            if ("Anulado".equals(estado)) {
                doc.add(new Paragraph("COMPROBANTE ANULADO", ANULADO));
            }
            doc.add(new Paragraph(" "));
            doc.add(new Paragraph("Fecha de recepción: " + fec.format(FH), NORMAL));
            doc.add(new Paragraph("Paciente destinatario: " + paciente, NORMAL));
            doc.add(new Paragraph("Entregado por: " + entregante + "  (CI " + ciEntregante + ")", NORMAL));
            doc.add(new Paragraph("Recibido por (enfermería): " + enfermero, NORMAL));
            doc.add(new Paragraph(" "));

            PdfPTable t = new PdfPTable(new float[] {4f, 2f, 1.2f, 2f, 2f});
            t.setWidthPercentage(100);
            for (String h : new String[] {"Medicamento", "Dosis", "Cant.", "Lote", "Vence"}) {
                PdfPCell cell = new PdfPCell(new Phrase(h, NEGRITA));
                cell.setBackgroundColor(new Color(230, 230, 230));
                cell.setPadding(5);
                t.addCell(cell);
            }
            t.addCell(celda(medicamento, Element.ALIGN_LEFT));
            t.addCell(celda(dosis, Element.ALIGN_LEFT));
            t.addCell(celda(String.valueOf(cantidad), Element.ALIGN_CENTER));
            t.addCell(celda(lote, Element.ALIGN_LEFT));
            t.addCell(celda(vence, Element.ALIGN_CENTER));
            doc.add(t);

            Paragraph v = new Paragraph(
                    "Verificación en dos capas: firma digital en pantalla y código OTP validado.", NORMAL);
            v.setSpacingBefore(15);
            doc.add(v);

            Paragraph pie = new Paragraph(
                    "Respaldo interno de la recepción de insumos aportados por el paciente o su familiar.", PIE);
            pie.setSpacingBefore(30);
            doc.add(pie);

            doc.close();
            return out.toByteArray();
        } catch (Exception e) {
            throw new ComprobanteException(500, "No se pudo generar el PDF");
        }
    }

    private static PdfPCell celda(String texto, int alineacion) {
        PdfPCell c = new PdfPCell(new Phrase(texto, NORMAL));
        c.setHorizontalAlignment(alineacion);
        c.setPadding(5);
        return c;
    }
}