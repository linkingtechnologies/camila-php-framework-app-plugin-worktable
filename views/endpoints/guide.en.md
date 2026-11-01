# Endpoints {#top}

The addresses other programs need to talk to this instance, and the steps to connect two AI assistants to it. The page only shows information: it changes nothing.

## The two addresses {#indirizzi}

At the top there are two read-only fields:

- **This app's MCP endpoint**: the address of the MCP server of this instance. It is the one to give to an AI assistant;
- **This app's REST API base URL**: the base address of the REST API, that is the `cf_api.php` of this application. The scripts of the [Tools](guide:tools) page need it.

Clicking in a field selects the whole text. **Copy** puts it on the clipboard and the page confirms with **Copied!**. If the browser refuses, the page says to select the text and copy it by hand.

## Connect Claude Desktop {#claude}

The box **Connect to Claude Desktop** gives the two steps:

1. generate an API token for your user (in the administration area: *Users*, edit your user, *Set API token*);
2. in Claude Desktop add a custom connector with the MCP endpoint above, or add to `claude_desktop_config.json` the entry shown under **Example claude_desktop_config.json entry**, replacing `<token>` with the token of step 1.

The example already contains the address of this instance. **Copy** copies it.

## Connect ChatGPT {#chatgpt}

The box **Connect to ChatGPT (Windows app / web)** lists the three steps in ChatGPT and, below them, two yellow warnings:

- if the MCP endpoint is not a public HTTPS address (for example when you work on `localhost`) ChatGPT cannot reach it: it has to be published behind HTTPS or exposed through a secure tunnel;
- this instance asks for a custom `X-API-Key` header, and it is not confirmed that ChatGPT lets you attach one: check before relying on the connection.

Unlike Claude Desktop, ChatGPT only connects to remote servers: it does not start local programs.

The [MCP Checker](guide:mcp-checker) page lets you test the endpoint before you connect an assistant to it.
