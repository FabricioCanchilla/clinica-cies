package bo.cies.quarkus.otp;

import java.nio.charset.StandardCharsets;
import java.security.MessageDigest;
import java.security.SecureRandom;
import java.sql.Connection;
import java.sql.PreparedStatement;
import java.sql.ResultSet;
import java.sql.SQLException;
import java.time.LocalDateTime;
import java.time.ZoneId;
import java.util.HexFormat;
import java.util.Set;
import java.util.concurrent.atomic.AtomicLong;

import javax.crypto.Mac;
import javax.crypto.spec.SecretKeySpec;

import org.eclipse.microprofile.config.inject.ConfigProperty;
import org.jboss.logging.Logger;

import bo.cies.quarkus.otp.OtpDtos.GenerarRequest;
import bo.cies.quarkus.otp.OtpDtos.GenerarResponse;
import bo.cies.quarkus.otp.OtpDtos.ValidarRequest;
import bo.cies.quarkus.otp.OtpDtos.ValidarResponse;
import io.agroal.api.AgroalDataSource;
import io.quarkus.mailer.Mail;
import io.quarkus.mailer.Mailer;
import jakarta.enterprise.context.ApplicationScoped;
import jakarta.inject.Inject;

@ApplicationScoped
public class OtpService {

    private static final Logger LOG = Logger.getLogger(OtpService.class);
    private static final ZoneId LA_PAZ = ZoneId.of("America/La_Paz");
    private static final Set<String> PROPOSITOS = Set.of("PAGO_CITA", "ENTREGA_INSUMOS");
    private static final Set<String> CANALES = Set.of("CORREO", "SMS");

    private final SecureRandom random = new SecureRandom();
    private final AtomicLong contador = new AtomicLong(-1);

    @Inject
    AgroalDataSource ds;

    @Inject
    Mailer mailer;

    @ConfigProperty(name = "cies.otp.secret")
    String secret;

    @ConfigProperty(name = "cies.otp.minutos", defaultValue = "5")
    int minutos;

    @ConfigProperty(name = "cies.otp.max-intentos", defaultValue = "3")
    int maxIntentos;

    // ===================== GENERAR =====================

    public GenerarResponse generar(GenerarRequest r) {
        if (r == null) {
            throw new OtpException(400, "Cuerpo de la peticion vacio");
        }
        String codUsr = limpio(r.codUsr());
        String proposito = limpio(r.proposito());
        String destino = limpio(r.destino());
        String canal = limpio(r.canal()).toUpperCase();

        if (codUsr.isEmpty() || codUsr.length() > 8) {
            throw new OtpException(400, "codUsr invalido");
        }
        if (!PROPOSITOS.contains(proposito)) {
            throw new OtpException(400, "proposito invalido (PAGO_CITA o ENTREGA_INSUMOS)");
        }
        if (!CANALES.contains(canal)) {
            throw new OtpException(400, "canal invalido (CORREO o SMS)");
        }
        if (destino.isEmpty() || destino.length() > 60) {
            throw new OtpException(400, "destino invalido");
        }
        if (canal.equals("CORREO") && !destino.matches("^[^@\\s]+@[^@\\s]+\\.[^@\\s]+$")) {
            throw new OtpException(400, "correo de destino invalido");
        }
        if (canal.equals("SMS") && !destino.matches("^\\d{8}$")) {
            throw new OtpException(400, "telefono de destino invalido (8 digitos)");
        }

        String codigo = String.format("%06d", random.nextInt(1_000_000));
        LocalDateTime ahora = LocalDateTime.now(LA_PAZ);
        LocalDateTime expira = ahora.plusMinutes(minutos);

        String codOtp = guardar(codUsr, proposito, canal, destino, codigo, ahora, expira);

        try {
            if (canal.equals("CORREO")) {
                enviarCorreo(destino, codigo);
            } else {
                // SMS simulado: no sale a ningun celular, solo queda en el log del sistema
                LOG.infof("[SMS SIMULADO] a %s: codigo %s (vence %s)", destino, codigo, expira);
            }
        } catch (Exception e) {
            LOG.errorf(e, "No se pudo enviar el OTP %s", codOtp);
            marcarExpirado(codOtp);
            throw new OtpException(502, "No se pudo enviar el codigo");
        }

        LOG.infof("OTP %s generado (usuario %s, %s, canal %s)", codOtp, codUsr, proposito, canal);
        return new GenerarResponse(codOtp, expira.toString(), canal, minutos * 60);
    }

       private String guardar(String codUsr, String proposito, String canal, String destino, String codigo,
                           LocalDateTime ahora, LocalDateTime expira) {
        String sql = "INSERT INTO OTP (COD_OTP, VAL_OTP, PRO_OTP, CAN_OTP, DST_OTP, FECEMI_OTP, FECEXP_OTP, "
                + "EST_OTP, INT_OTP, COD_USR) VALUES (?, ?, ?, ?, ?, ?, ?, 'Pendiente', 0, ?)";
        try (Connection c = ds.getConnection()) {
            c.setAutoCommit(false);
            try {
                // Un solo codigo vigente por usuario y proposito
                try (PreparedStatement ps = c.prepareStatement(
                        "UPDATE OTP SET EST_OTP='Expirado' WHERE COD_USR=? AND PRO_OTP=? AND EST_OTP='Pendiente'")) {
                    ps.setString(1, codUsr);
                    ps.setString(2, proposito);
                    ps.executeUpdate();
                }
                for (int intento = 0; intento < 5; intento++) {
                    String codOtp = siguienteCodigo(c);
                    try (PreparedStatement ps = c.prepareStatement(sql)) {
                        ps.setString(1, codOtp);
                        ps.setString(2, hash(codOtp, codigo));
                        ps.setString(3, proposito);
                        ps.setString(4, canal);
                        ps.setString(5, destino);
                        ps.setObject(6, ahora);
                        ps.setObject(7, expira);
                        ps.setString(8, codUsr);
                        ps.executeUpdate();
                        c.commit();
                        return codOtp;
                    } catch (SQLException e) {
                        if (e.getErrorCode() == 1062) {
                            continue; // codigo repetido, probamos el siguiente
                        }
                        if (e.getErrorCode() == 1452) {
                            c.rollback();
                            throw new OtpException(400, "El usuario no existe");
                        }
                        throw e;
                    }
                }
                c.rollback();
                throw new OtpException(503, "No se pudo asignar un codigo OTP, reintente");
            } catch (SQLException e) {
                c.rollback();
                throw e;
            }
        } catch (SQLException e) {
            LOG.error("Error de base de datos al generar OTP", e);
            throw new OtpException(503, "Base de datos no disponible");
        }
    }

    private synchronized String siguienteCodigo(Connection c) throws SQLException {
        if (contador.get() < 0) {
            try (PreparedStatement ps = c.prepareStatement(
                    "SELECT COALESCE(MAX(CAST(SUBSTRING(COD_OTP, 4) AS UNSIGNED)), 0) FROM OTP");
                 ResultSet rs = ps.executeQuery()) {
                rs.next();
                contador.set(rs.getLong(1));
            }
        }
        return String.format("OTP%07d", contador.incrementAndGet());
    }

    private void enviarCorreo(String destino, String codigo) {
        String cuerpo = "Clínica CIES\n\n"
                + "Tu código de verificación es: " + codigo + "\n\n"
                + "Vence en " + minutos + " minutos y solo se puede usar una vez.\n"
                + "Si no solicitaste este código, ignora este mensaje.";
        mailer.send(Mail.withText(destino, "Código de verificación - Clínica CIES", cuerpo));
    }

    private void marcarExpirado(String codOtp) {
        try (Connection c = ds.getConnection();
             PreparedStatement ps = c.prepareStatement(
                     "UPDATE OTP SET EST_OTP='Expirado' WHERE COD_OTP=? AND EST_OTP='Pendiente'")) {
            ps.setString(1, codOtp);
            ps.executeUpdate();
        } catch (SQLException e) {
            LOG.error("No se pudo marcar el OTP como expirado", e);
        }
    }

    // ===================== VALIDAR =====================

    public ValidarResponse validar(ValidarRequest r) {
        if (r == null || limpio(r.codOtp()).isEmpty() || limpio(r.codigo()).isEmpty()) {
            throw new OtpException(400, "codOtp y codigo son obligatorios");
        }
        String codOtp = limpio(r.codOtp());
        String codigo = limpio(r.codigo());
        LocalDateTime ahora = LocalDateTime.now(LA_PAZ);

        try (Connection c = ds.getConnection()) {
            String estado;
            String hashGuardado;
            LocalDateTime expira;

            try (PreparedStatement ps = c.prepareStatement(
                    "SELECT EST_OTP, VAL_OTP, FECEXP_OTP FROM OTP WHERE COD_OTP=?")) {
                ps.setString(1, codOtp);
                try (ResultSet rs = ps.executeQuery()) {
                    if (!rs.next()) {
                        return new ValidarResponse(false, "NO_EXISTE", 0);
                    }
                    estado = rs.getString(1);
                    hashGuardado = rs.getString(2);
                    expira = rs.getObject(3, LocalDateTime.class);
                }
            }

            if ("Verificado".equals(estado)) {
                return new ValidarResponse(false, "YA_USADO", 0);
            }
            if ("Bloqueado".equals(estado)) {
                return new ValidarResponse(false, "BLOQUEADO", 0);
            }
            if ("Expirado".equals(estado)) {
                return new ValidarResponse(false, "EXPIRADO", 0);
            }

            if (ahora.isAfter(expira)) {
                try (PreparedStatement ps = c.prepareStatement(
                        "UPDATE OTP SET EST_OTP='Expirado' WHERE COD_OTP=? AND EST_OTP='Pendiente'")) {
                    ps.setString(1, codOtp);
                    ps.executeUpdate();
                }
                LOG.infof("OTP %s vencido", codOtp);
                return new ValidarResponse(false, "EXPIRADO", 0);
            }

            boolean coincide = MessageDigest.isEqual(
                    hash(codOtp, codigo).getBytes(StandardCharsets.UTF_8),
                    hashGuardado.getBytes(StandardCharsets.UTF_8));

            if (!coincide) {
                // En MySQL los SET se evaluan de izquierda a derecha: el CASE usa el INT_OTP anterior
                try (PreparedStatement ps = c.prepareStatement(
                        "UPDATE OTP SET EST_OTP = CASE WHEN INT_OTP + 1 >= ? THEN 'Bloqueado' ELSE EST_OTP END, "
                                + "INT_OTP = INT_OTP + 1 WHERE COD_OTP=? AND EST_OTP='Pendiente'")) {
                    ps.setInt(1, maxIntentos);
                    ps.setString(2, codOtp);
                    ps.executeUpdate();
                }
                int intentos = 0;
                try (PreparedStatement ps = c.prepareStatement("SELECT INT_OTP FROM OTP WHERE COD_OTP=?")) {
                    ps.setString(1, codOtp);
                    try (ResultSet rs = ps.executeQuery()) {
                        if (rs.next()) {
                            intentos = rs.getInt(1);
                        }
                    }
                }
                int restantes = Math.max(0, maxIntentos - intentos);
                LOG.warnf("OTP %s: codigo incorrecto (intentos %d)", codOtp, intentos);
                return new ValidarResponse(false, restantes == 0 ? "BLOQUEADO" : "CODIGO_INCORRECTO", restantes);
            }

            // Un solo uso: el UPDATE condicional evita que dos peticiones a la vez validen el mismo codigo
            int filas;
            try (PreparedStatement ps = c.prepareStatement(
                    "UPDATE OTP SET EST_OTP='Verificado' WHERE COD_OTP=? AND EST_OTP='Pendiente' AND FECEXP_OTP >= ?")) {
                ps.setString(1, codOtp);
                ps.setObject(2, ahora);
                filas = ps.executeUpdate();
            }
            if (filas == 1) {
                LOG.infof("OTP %s verificado", codOtp);
                return new ValidarResponse(true, "OK", 0);
            }
            return new ValidarResponse(false, "YA_USADO", 0);

        } catch (SQLException e) {
            LOG.error("Error de base de datos al validar OTP", e);
            throw new OtpException(503, "Base de datos no disponible");
        }
    }

    // ===================== UTILIDADES =====================

    private String hash(String codOtp, String codigo) {
        try {
            Mac mac = Mac.getInstance("HmacSHA256");
            mac.init(new SecretKeySpec(secret.getBytes(StandardCharsets.UTF_8), "HmacSHA256"));
            byte[] h = mac.doFinal((codOtp + ":" + codigo).getBytes(StandardCharsets.UTF_8));
            return HexFormat.of().formatHex(h);
        } catch (Exception e) {
            throw new IllegalStateException("No se pudo calcular el hash del OTP", e);
        }
    }

    private static String limpio(String s) {
        return s == null ? "" : s.trim();
    }
}