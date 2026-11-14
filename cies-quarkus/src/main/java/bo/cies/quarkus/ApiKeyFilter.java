package bo.cies.quarkus;

import jakarta.ws.rs.container.ContainerRequestContext;
import jakarta.ws.rs.container.ContainerRequestFilter;
import jakarta.ws.rs.core.Response;
import jakarta.ws.rs.ext.Provider;
import org.eclipse.microprofile.config.inject.ConfigProperty;

@Provider
public class ApiKeyFilter implements ContainerRequestFilter {

    @ConfigProperty(name = "cies.api-key")
    String apiKey;

    @Override
    public void filter(ContainerRequestContext ctx) {
        String path = ctx.getUriInfo().getPath();
        // /health queda libre para el healthcheck de Docker
        if (path.startsWith("/q/health") || path.equals("/api/health")) {
            return;
        }
        String enviada = ctx.getHeaderString("X-API-KEY");
        if (enviada == null || !enviada.equals(apiKey)) {
            ctx.abortWith(Response.status(Response.Status.UNAUTHORIZED)
                    .entity("{\"error\":\"API key invalida\"}")
                    .type("application/json").build());
        }
    }
}