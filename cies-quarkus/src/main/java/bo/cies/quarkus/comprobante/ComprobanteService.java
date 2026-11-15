package bo.cies.quarkus.comprobante;

import java.math.BigDecimal;
import java.math.RoundingMode;
import java.sql.Connection;
import java.sql.PreparedStatement;
import java.sql.ResultSet;
import java.sql.SQLException;
import java.time.LocalDate;
import java.time.LocalDateTime;
import java.time.ZoneId;
import java.util.ArrayList;
import java.util.List;
import java.util.regex.Pattern;

import org.jboss.logging.Logger;

import bo.cies.quarkus.comprobante.ComprobanteDtos.EmitirRequest;
import bo.cies.quarkus.comprobante.ComprobanteDtos.EmitirResponse;
import bo.cies.quarkus.comprobante.ComprobanteDtos.EntregaRequest;
import bo.cies.quarkus.comprobante.ComprobanteDtos.EntregaResponse;
import bo.cies.quarkus.comprobante.ComprobanteDtos.Item;
import bo.cies.quarkus.comprobante.ComprobantePdf.Linea;
import io.agroal.api.AgroalDataSource;
import jakarta.enterprise.context.ApplicationScoped;
import jakarta.inject.Inject;

@ApplicationScoped
public class ComprobanteService {

    private static final Logger LOG = Logger.getLogger(ComprobanteService.class);
    private static final ZoneId LA_PAZ = ZoneId.of("America/La_Paz");
    private static final Pattern NRO_COM = Pattern.compile("^COM\\d{8}$");
    private static final Pattern NRO_CEN = Pattern.compile("^CEN\\d{8}$");
    private static final BigDecimal CIEN = new BigDecimal("100");
    private static final BigDecimal PRECIO_MAX = new BigDecimal("999999.99");

    @Inject
    AgroalDataSource ds;

    // ===================== EMITIR COMPROBANTE DE PAGO =====================

    public EmitirResponse emitir(EmitirRequest r) {
        if (r == null) {
            throw new ComprobanteException(400, "Cuerpo de la peticion vacio");
        }
        String codCit = limpio(r.codCit());
        String codEmp = limpio(r.codEmp());
        String codCaj = limpio(r.codCaj());

        if (codCit.isEmpty() || codCit.length() > 10) {
            throw new ComprobanteException(400, "codCit invalido");
        }
        if (codEmp.length() > 9 || codCaj.length() > 10) {
            throw new ComprobanteException(400, "codEmp o codCaj invalido");
        }
        if (codEmp.isEmpty() != codCaj.isEmpty()) {
            throw new ComprobanteException(400, "codEmp y codCaj van juntos: ambos o ninguno (pago en linea)");
        }
        List<Item> items = r.items();
        if (items == null || items.isEmpty() || items.size() > 50) {
            throw new ComprobanteException(400, "items debe tener entre 1 y 50 lineas");
        }

        // Se valida cada linea y el total se calcula aqui: no se confia en un total enviado por el cliente
        List<Linea> lineas = new ArrayList<>();
        BigDecimal total = BigDecimal.ZERO;
        for (Item it : items) {
            if (it == null) {
                throw new ComprobanteException(400, "linea vacia");
            }
            String codSrv = limpio(it.codSrv());
            if (codSrv.isEmpty() || codSrv.length() > 8) {
                throw new ComprobanteException(400, "codSrv invalido");
            }
            int cant = it.cantidad() == null ? 0 : it.cantidad();
            if (cant < 1 || cant > 1000) {
                throw new ComprobanteException(400, "cantidad invalida (1 a 1000)");
            }
            BigDecimal precio = it.precio();
            if (precio == null || precio.signum() < 0 || precio.compareTo(PRECIO_MAX) > 0) {
                throw new ComprobanteException(400, "precio invalido");
            }
            precio = precio.setScale(2, RoundingMode.HALF_UP);
            int desc = it.descuento() == null ? 0 : it.descuento();
            if (desc < 0 || desc > 100) {
                throw new ComprobanteException(400, "descuento invalido (0 a 100)");
            }
            BigDecimal subtotal = precio.multiply(BigDecimal.valueOf(cant))
                    .multiply(CIEN.subtract(BigDecimal.valueOf(desc)))
                    .divide(CIEN, 2, RoundingMode.HALF_UP);
            total = total.add(subtotal);
            lineas.add(new Linea(codSrv, cant, precio, desc, subtotal));
        }

        try (Connection c = ds.getConnection()) {
            // El paciente sale de la cita, no de lo que mande el cliente
            String codPac;
            try (PreparedStatement ps = c.prepareStatement("SELECT COD_PAC FROM CITA WHERE COD_CIT=?")) {
                ps.setString(1, codCit);
                try (ResultSet rs = ps.executeQuery()) {
                    if (!rs.next()) {
                        throw new ComprobanteException(404, "La cita no existe");
                    }
                    codPac = rs.getString(1);
                }
            }

            c.setAutoCommit(false);
            for (int intento = 0; intento < 5; intento++) {
                try {
                    String nro = String.format("COM%08d", maximo(c, "COMPROBANTE", "NRO_COM") + 1);
                    LocalDateTime ahora = LocalDateTime.now(LA_PAZ);

                    try (PreparedStatement ps = c.prepareStatement(
                            "INSERT INTO COMPROBANTE (NRO_COM, FEC_COM, MONTO_COM, EST_COM, COD_PAC, COD_CIT, COD_EMP, COD_CAJ) "
                                    + "VALUES (?, ?, ?, 'Emitido', ?, ?, ?, ?)")) {
                        ps.setString(1, nro);
                        ps.setObject(2, ahora);
                        ps.setBigDecimal(3, total);
                        ps.setString(4, codPac);
                        ps.setString(5, codCit);
                        ps.setString(6, codEmp.isEmpty() ? null : codEmp);
                        ps.setString(7, codCaj.isEmpty() ? null : codCaj);
                        ps.executeUpdate();
                    }

                    long dco = maximo(c, "DETALLE_COMPROBANTE", "COD_DCO");
                    try (PreparedStatement ps = c.prepareStatement(
                            "INSERT INTO DETALLE_COMPROBANTE (COD_DCO, NRO_COM, COD_SRV, CAN_DCO, PRE_DCO, DESC_DCO) "
                                    + "VALUES (?, ?, ?, ?, ?, ?)")) {
                        for (Linea l : lineas) {
                            dco++;
                            ps.setString(1, String.format("DCO%07d", dco));
                            ps.setString(2, nro);
                            ps.setString(3, l.codSrv());
                            ps.setInt(4, l.cantidad());
                            ps.setBigDecimal(5, l.precio());
                            ps.setInt(6, l.descuento());
                            ps.executeUpdate();
                        }
                    }

                    c.commit();
                    LOG.infof("Comprobante %s emitido (cita %s, Bs %s, %d lineas)", nro, codCit, total, lineas.size());
                    return new EmitirResponse(nro, total, "Emitido", ahora.toString());
                } catch (SQLException e) {
                    c.rollback();
                    int cod = e.getErrorCode();
                    if (cod == 1062 || cod == 1213 || cod == 1205) {
                        continue; // numero repetido, deadlock o espera: reintentamos con el siguiente
                    }
                    if (cod == 1452) {
                        throw new ComprobanteException(400, "El empleado, la caja o un servicio no existe");
                    }
                    if (cod == 3819) {
                        throw new ComprobanteException(400, "Los datos no cumplen las reglas de la base de datos");
                    }
                    throw e;
                }
            }
            throw new ComprobanteException(503, "No se pudo asignar el numero de comprobante, reintente");
        } catch (SQLException e) {
            LOG.error("Error de base de datos al emitir comprobante", e);
            throw new ComprobanteException(503, "Base de datos no disponible");
        }
    }

    // ===================== EMITIR COMPROBANTE DE ENTREGA =====================

    public EntregaResponse emitirEntrega(EntregaRequest r) {
        if (r == null) {
            throw new ComprobanteException(400, "Cuerpo de la peticion vacio");
        }
        String codIns = limpio(r.codIns());
        String codEmp = limpio(r.codEmp());
        if (codIns.isEmpty() || codIns.length() > 10) {
            throw new ComprobanteException(400, "codIns invalido");
        }
        if (codEmp.isEmpty() || codEmp.length() > 9) {
            throw new ComprobanteException(400, "codEmp invalido");
        }

        try (Connection c = ds.getConnection()) {
            try (PreparedStatement ps = c.prepareStatement(
                    "SELECT EST_INS, FIR_INS FROM INSUMO_TERCERO WHERE COD_INS=?")) {
                ps.setString(1, codIns);
                try (ResultSet rs = ps.executeQuery()) {
                    if (!rs.next()) {
                        throw new ComprobanteException(404, "El insumo no existe");
                    }
                    // RN36: el comprobante se emite solo con el insumo verificado (firma + OTP)
                    if (!"Verificado".equals(rs.getString(1)) || rs.getString(2) == null) {
                        throw new ComprobanteException(409, "El insumo aun no esta verificado con firma y OTP");
                    }
                }
            }

            // Uno por insumo: si ya hay uno emitido, se devuelve ese (permite reintentar sin duplicar)
            try (PreparedStatement ps = c.prepareStatement(
                    "SELECT NRO_CEN, FEC_CEN FROM COMPROBANTE_ENTREGA WHERE COD_INS=? AND EST_CEN='Emitido'")) {
                ps.setString(1, codIns);
                try (ResultSet rs = ps.executeQuery()) {
                    if (rs.next()) {
                        return new EntregaResponse(rs.getString(1), "Emitido",
                                rs.getObject(2, LocalDateTime.class).toString(), true);
                    }
                }
            }

            c.setAutoCommit(false);
            for (int intento = 0; intento < 5; intento++) {
                try {
                    String nro = String.format("CEN%08d", maximo(c, "COMPROBANTE_ENTREGA", "NRO_CEN") + 1);
                    LocalDateTime ahora = LocalDateTime.now(LA_PAZ);
                    try (PreparedStatement ps = c.prepareStatement(
                            "INSERT INTO COMPROBANTE_ENTREGA (NRO_CEN, FEC_CEN, EST_CEN, COD_INS, COD_EMP) "
                                    + "VALUES (?, ?, 'Emitido', ?, ?)")) {
                        ps.setString(1, nro);
                        ps.setObject(2, ahora);
                        ps.setString(3, codIns);
                        ps.setString(4, codEmp);
                        ps.executeUpdate();
                    }
                    c.commit();
                    LOG.infof("Comprobante de entrega %s emitido (insumo %s)", nro, codIns);
                    return new EntregaResponse(nro, "Emitido", ahora.toString(), false);
                } catch (SQLException e) {
                    c.rollback();
                    int cod = e.getErrorCode();
                    if (cod == 1062 || cod == 1213 || cod == 1205) {
                        continue;
                    }
                    if (cod == 1452) {
                        throw new ComprobanteException(400, "El empleado no existe");
                    }
                    throw e;
                }
            }
            throw new ComprobanteException(503, "No se pudo asignar el numero de comprobante, reintente");
        } catch (SQLException e) {
            LOG.error("Error de base de datos al emitir comprobante de entrega", e);
            throw new ComprobanteException(503, "Base de datos no disponible");
        }
    }

    // ===================== PDF =====================

    public byte[] pdfPago(String nro) {
        nro = limpio(nro);
        if (!NRO_COM.matcher(nro).matches()) {
            throw new ComprobanteException(400, "Numero de comprobante invalido");
        }
        try (Connection c = ds.getConnection()) {
            LocalDateTime fec;
            BigDecimal monto;
            String estado, codCit, paciente, ci, cita, emisor;

            try (PreparedStatement ps = c.prepareStatement(
                    "SELECT c.FEC_COM, c.MONTO_COM, c.EST_COM, c.COD_CIT, p.NOM_PAC, p.APE_PAC, p.CI_PAC, "
                            + "ci.FEC_CIT, ci.HOR_CIT, e.NOM_EMP, e.APE_EMP "
                            + "FROM COMPROBANTE c "
                            + "JOIN PACIENTE p ON p.COD_PAC = c.COD_PAC "
                            + "JOIN CITA ci ON ci.COD_CIT = c.COD_CIT "
                            + "LEFT JOIN EMPLEADO e ON e.COD_EMP = c.COD_EMP "
                            + "WHERE c.NRO_COM = ?")) {
                ps.setString(1, nro);
                try (ResultSet rs = ps.executeQuery()) {
                    if (!rs.next()) {
                        throw new ComprobanteException(404, "El comprobante no existe");
                    }
                    fec = rs.getObject(1, LocalDateTime.class);
                    monto = rs.getBigDecimal(2);
                    estado = rs.getString(3);
                    codCit = rs.getString(4);
                    paciente = rs.getString(5) + " " + rs.getString(6);
                    ci = rs.getString(7);
                    cita = ComprobantePdf.fecha(rs.getObject(8, LocalDate.class)) + " " + rs.getString(9).substring(0, 5);
                    emisor = rs.getString(10) == null ? "Pago en línea" : rs.getString(10) + " " + rs.getString(11);
                }
            }

            List<Linea> lineas = new ArrayList<>();
            try (PreparedStatement ps = c.prepareStatement(
                    "SELECT s.NOM_SRV, d.CAN_DCO, d.PRE_DCO, d.DESC_DCO FROM DETALLE_COMPROBANTE d "
                            + "JOIN SERVICIO s ON s.COD_SRV = d.COD_SRV WHERE d.NRO_COM = ? ORDER BY d.id")) {
                ps.setString(1, nro);
                try (ResultSet rs = ps.executeQuery()) {
                    while (rs.next()) {
                        int cant = rs.getInt(2);
                        BigDecimal precio = rs.getBigDecimal(3);
                        int desc = rs.getInt(4);
                        BigDecimal sub = precio.multiply(BigDecimal.valueOf(cant))
                                .multiply(CIEN.subtract(BigDecimal.valueOf(desc)))
                                .divide(CIEN, 2, RoundingMode.HALF_UP);
                        lineas.add(new Linea(rs.getString(1), cant, precio, desc, sub));
                    }
                }
            }

            return ComprobantePdf.pago(nro, fec, estado, paciente, ci, codCit, cita, emisor, lineas, monto);
        } catch (SQLException e) {
            LOG.error("Error de base de datos al generar el PDF", e);
            throw new ComprobanteException(503, "Base de datos no disponible");
        }
    }

    public byte[] pdfEntrega(String nro) {
        nro = limpio(nro);
        if (!NRO_CEN.matcher(nro).matches()) {
            throw new ComprobanteException(400, "Numero de comprobante invalido");
        }
        try (Connection c = ds.getConnection();
             PreparedStatement ps = c.prepareStatement(
                     "SELECT ce.FEC_CEN, ce.EST_CEN, m.DES_MED, i.DOS_INS, i.CAN_INS, i.LOT_INS, i.FECVEN_INS, "
                             + "pa.NOM_PAC, pa.APE_PAC, en.NOM_ENT, en.APE_ENT, en.CI_ENT, em.NOM_EMP, em.APE_EMP "
                             + "FROM COMPROBANTE_ENTREGA ce "
                             + "JOIN INSUMO_TERCERO i ON i.COD_INS = ce.COD_INS "
                             + "JOIN MEDICAMENTO m ON m.COD_MED = i.COD_MED "
                             + "JOIN PACIENTE pa ON pa.COD_PAC = i.COD_PAC "
                             + "JOIN ENTREGANTE en ON en.COD_ENT = i.COD_ENT "
                             + "JOIN EMPLEADO em ON em.COD_EMP = ce.COD_EMP "
                             + "WHERE ce.NRO_CEN = ?")) {
            ps.setString(1, nro);
            try (ResultSet rs = ps.executeQuery()) {
                if (!rs.next()) {
                    throw new ComprobanteException(404, "El comprobante no existe");
                }
                return ComprobantePdf.entrega(nro,
                        rs.getObject(1, LocalDateTime.class),
                        rs.getString(2),
                        rs.getString(3),
                        rs.getString(4),
                        rs.getInt(5),
                        rs.getString(6),
                        ComprobantePdf.fecha(rs.getObject(7, LocalDate.class)),
                        rs.getString(8) + " " + rs.getString(9),
                        rs.getString(10) + " " + rs.getString(11),
                        rs.getString(12),
                        rs.getString(13) + " " + rs.getString(14));
            }
        } catch (SQLException e) {
            LOG.error("Error de base de datos al generar el PDF de entrega", e);
            throw new ComprobanteException(503, "Base de datos no disponible");
        }
    }

    // ===================== UTILIDADES =====================

    /** Mayor correlativo numerico de una columna con prefijo de 3 letras (COM, CEN, DCO). Nombres internos, no del usuario. */
    private long maximo(Connection c, String tabla, String columna) throws SQLException {
        String sql = "SELECT COALESCE(MAX(CAST(SUBSTRING(" + columna + ", 4) AS UNSIGNED)), 0) FROM " + tabla;
        try (PreparedStatement ps = c.prepareStatement(sql); ResultSet rs = ps.executeQuery()) {
            rs.next();
            return rs.getLong(1);
        }
    }

    private static String limpio(String s) {
        return s == null ? "" : s.trim();
    }
}