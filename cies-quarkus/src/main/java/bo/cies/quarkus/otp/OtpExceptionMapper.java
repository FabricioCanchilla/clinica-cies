package bo.cies.quarkus.otp;

import java.util.Map;

import jakarta.ws.rs.core.MediaType;
import jakarta.ws.rs.core.Response;
import jakarta.ws.rs.ext.ExceptionMapper;
import jakarta.ws.rs.ext.Provider;

@Provider
public class OtpExceptionMapper implements ExceptionMapper<OtpException> {

    @Override
    public Response toResponse(OtpException e) {
        return Response.status(e.getStatus())
                .entity(Map.of("error", e.getMessage()))
                .type(MediaType.APPLICATION_JSON)
                .build();
    }
}