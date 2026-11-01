# MCP Checker {#top}

A diagnostic tool: you give it the address of an MCP server (Model Context Protocol, the way AI assistants reach external tools), and it tells you whether the server answers and which tools it offers. It is read-only: it cannot change any data. Use it before you connect an assistant to an endpoint.

## Enter the endpoint {#endpoint}

The page opens with the first field already filled with the MCP endpoint of this instance (the same shown in [Endpoints](guide:endpoints)), so testing your own server needs no typing.

- **MCP endpoint URL**: the address to test, starting with `http://` or `https://`. Replace it to test another server;
- **Authorization header (optional)**: the value of the `Authorization` header, for example `Bearer <token>`. Leave it empty if the server needs none.

If you change either field after a test, the previous results disappear at once: nothing is shown until you press the button again.

## Connect {#connetti}

**Connect** runs the test: the handshake with the server, then the request for its tools. While it runs the button reads **Connecting…** and is disabled. The requests go through the plugin's server, so the browser does not need to reach the endpoint itself.

If the address is empty or is not an `http://` or `https://` one, the page says **Enter a valid http:// or https:// URL.** and sends nothing.

## Read the result {#risultato}

On success the box **Server** shows the **Name**, the **Version** and the **Protocol version** the server declares. Below, **Tools (N)** lists the tools one by one with their description and, under **Input schema**, the parameters each one accepts.

If the server has no tools the page says **This server does not expose any tools.**

## When it does not work {#errori}

A red message says which step failed and why:

- *Handshake (initialize) failed*: the server was not reached, or it refused the handshake. No server information and no tools are shown;
- *Listing tools failed*: the handshake worked but the list did not. The server information stays on screen.

A server that needs authentication and receives none, or receives a wrong value, shows up as one of these two errors with the message the server returned.
