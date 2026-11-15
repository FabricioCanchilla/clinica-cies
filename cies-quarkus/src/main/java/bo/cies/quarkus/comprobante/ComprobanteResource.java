package bo.cies.quarkus.comprobante;

import bo.cies.quarkus.comprobante.ComprobanteDtos.EmitirRequest;
import bo.cies.quarkus.comprobante.ComprobanteDtos.EmitirResponse;
import bo.cies.quarkus.comprobante.ComprobanteDtos.EntregaRequest;
import bo.cies.quarkus.comprobante.ComprobanteDtos.EntregaResponse;
import jakarta.inject.Inject;
import jakarta.ws.rs.Consumes;
import jakarta.ws.rs.GET;
import jakarta.ws.rs.POST;
import jakarta.ws.rs.Path;
import jakarta.ws.rs.PathParam;
import jakarta.ws.rs.Produces;
import jakarta.ws.rs.core.MediaType;
import jakarta.ws.rs.core.Response;

@Path("/api/comprobantes")
@Consumes(MediaType.APPLICATION_JSON)
@Produces(MediaType.APPLICATION_JSON)
public class ComprobanteResource {

    @Inject
    ComprobanteService service;

    @POST
    public EmitirResponse emitir(EmitirRequest request) {
        return service.emitir(request);
    }

    @POST
    @Path("/entrega")
    public EntregaResponse emitirEntrega(EntregaRequest request) {
        return service.emitirEntrega(request);
    }

    @GET
    @Path("/{nro}/pdf")
    @Produces("application/pdf")
    public Response pdf(@PathParam("nro") String nro) {
        byte[] pdf = service.pdfPago(nro);
        return Response.ok(pdf)
                .header("Content-Disposition", "inline; filename=\"" + nro + ".pdf\"")
                .build();
    }

    @GET
    @Path("/entrega/{nro}/pdf")
    @Produces("application/pdf")
    public Response pdfEntrega(@PathParam("nro") String nro) {
        byte[] pdf = service.pdfEntrega(nro);
        return Response.ok(pdf)
                .header("Content-Disposition", "inline; filename=\"" + nro + ".pdf\"")
                .build();
    }
}