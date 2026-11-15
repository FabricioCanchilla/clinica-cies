package bo.cies.quarkus.comprobante;

import java.math.BigDecimal;
import java.util.List;

public final class ComprobanteDtos {

    private ComprobanteDtos() {
    }

    /** Una línea del comprobante. El precio es el precio unitario aplicado (para el anticipo, el monto del anticipo). */
    public record Item(String codSrv, Integer cantidad, BigDecimal precio, Integer descuento) {
    }

    /** codEmp y codCaj van juntos (ambos o ninguno): ninguno = anticipo pagado en línea. */
    public record EmitirRequest(String codCit, String codEmp, String codCaj, List<Item> items) {
    }

    public record EmitirResponse(String nroCom, BigDecimal monto, String estado, String fecha) {
    }

    public record EntregaRequest(String codIns, String codEmp) {
    }

    public record EntregaResponse(String nroCen, String estado, String fecha, boolean yaExistia) {
    }
}