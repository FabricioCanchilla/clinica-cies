package bo.cies.quarkus.otp;

import bo.cies.quarkus.otp.OtpDtos.GenerarRequest;
import bo.cies.quarkus.otp.OtpDtos.GenerarResponse;
import bo.cies.quarkus.otp.OtpDtos.ValidarRequest;
import bo.cies.quarkus.otp.OtpDtos.ValidarResponse;
import jakarta.inject.Inject;
import jakarta.ws.rs.Consumes;
import jakarta.ws.rs.POST;
import jakarta.ws.rs.Path;
import jakarta.ws.rs.Produces;
import jakarta.ws.rs.core.MediaType;

@Path("/api/otp")
@Consumes(MediaType.APPLICATION_JSON)
@Produces(MediaType.APPLICATION_JSON)
public class OtpResource {

    @Inject
    OtpService service;

    @POST
    @Path("/generar")
    public GenerarResponse generar(GenerarRequest request) {
        return service.generar(request);
    }

    @POST
    @Path("/validar")
    public ValidarResponse validar(ValidarRequest request) {
        return service.validar(request);
    }
}