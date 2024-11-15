
# Sanity Checks

1. [ ] Docker containers are running
   - [ ] `porta` 
   - [ ] `porta-db` / `porta-db-2` / `porta-db-3` (name will be machine dependent)
   - [ ] `porta-socket-server` 
   - [ ] `porta-redis` 
2. [ ] Porta is being accessed by its machine IP address in the browser: `http://XXX.XXX.XX.XXX:8080` (not `localhost`)
   - where `XXX.XXX.XX.XXX` is the current PX's IPv4 address (usually under `d3net` in Powershell's `ipconfig` output)
3. [ ] When sending/saving a page --> the page in the playlist has a channel selected
4. [ ] Socket Server URL
   - [ ] Is `http://porta-socket.server:6001` (must not be IP-based)
   - [ ] Bridge Socket Server URL is  `http://porta-socket.server:6001` or `http://XXX.XXX.XX.XXX:6001`
     - where `XXX.XXX.XX.XXX` is the current PX's IPv4 address (usually under `d3net` in Powershell's `ipconfig` output)
5. [ ] Database status icon color is green
6. [ ] Designer is currently running
7. [ ] (For QA machines) Designer version is correct; i.e., supports API `/experimental` endpoint
    - [ ] Designer API docs "Try it out" working for `/experimental/sockpuppet/patches`?
8. [ ] The Designer active project is the expected project for Porta's selected page/template/channel
9. [ ] Bridge API URL is `http://XXX.XXX.XX.XXX:8000`
   - where `XXX.XXX.XX.XXX` is the current PX's IPv4 address (usually under `d3net` in Powershell's `ipconfig` output)
10. [ ] In Porta's menu under `Window` > `Settings` > `Channels`, the correct channels are listed 
    - if not, use the sync button in the channel panel
11. [ ] In Porta's menu under `Window` > `Settings` > `Preference`, "Enable Play Groups" and "Enable play group sequence" are enabled 
    - blue/cyan is enabled, grey is disabled
12. [ ] In Porta's menu under `Window` > `Settings` > `Preference`, "Local Live Preview" is set as desired 
    - blue/cyan is enabled, grey is disabled

