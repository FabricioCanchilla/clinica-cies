package bo.cies.quarkus;

import jakarta.inject.Inject;
import jakarta.ws.rs.GET;
import jakarta.ws.rs.Path;
import jakarta.ws.rs.Produces;
import jakarta.ws.rs.core.MediaType;
import jakarta.ws.rs.core.Response;
import io.agroal.api.AgroalDataSource;

import java.sql.Connection;
import java.sql.ResultSet;
import java.sql.Statement;

@Path("/api/health")
@Produces(MediaType.APPLICATION_JSON)
public class HealthResource {

    @Inject
    AgroalDataSource ds;

    @GET
    public Response health() {
        try (Connection c = ds.getConnection();
             Statement st = c.createStatement();
             ResultSet rs = st.executeQuery("SELECT COUNT(*) FROM OTP")) {
            rs.next();
            return Response.ok("{\"estado\":\"ok\",\"bd\":\"ok\"}").build();
        } catch (Exception e) {
            return Response.status(503)
                    .entity("{\"estado\":\"error\",\"bd\":\"sin acceso\"}").build();
        }
    }
}