package bo.cies.quarkus.otp;

public final class OtpDtos {

    private OtpDtos() {}

    public record GenerarRequest(String codUsr, String proposito, String destino, String canal) {}

    public record GenerarResponse(String codOtp, String expira, String canal, int segundosValidez) {}

    public record ValidarRequest(String codOtp, String codigo) {}

    public record ValidarResponse(boolean valido, String motivo, int intentosRestantes) {}
}