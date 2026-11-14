package bo.cies.quarkus.otp;

public class OtpException extends RuntimeException {

    private final int status;

    public OtpException(int status, String mensaje) {
        super(mensaje);
        this.status = status;
    }

    public int getStatus() {
        return status;
    }
}