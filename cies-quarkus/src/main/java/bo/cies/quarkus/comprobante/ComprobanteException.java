package bo.cies.quarkus.comprobante;

public class ComprobanteException extends RuntimeException {

    private final int status;

    public ComprobanteException(int status, String mensaje) {
        super(mensaje);
        this.status = status;
    }

    public int getStatus() {
        return status;
    }
}