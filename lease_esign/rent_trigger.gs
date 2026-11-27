function rentReminderRun() {
  const p = PropertiesService.getScriptProperties();
  const url = p.getProperty('RENT_API_URL');
  const key = p.getProperty('RENT_API_KEY');
  if (!url || !key) throw new Error('Set RENT_API_URL and RENT_API_KEY');
  const r = UrlFetchApp.fetch(url, {method:'post', headers:{'X-Rent-Key':key}, muteHttpExceptions:true});
  if (r.getResponseCode() !== 200) throw new Error('HTTP '+r.getResponseCode());
  console.log(r.getContentText());
}
function installRentTrigger() {
  ScriptApp.getProjectTriggers().filter(t=>t.getHandlerFunction()==='rentReminderRun').forEach(t=>ScriptApp.deleteTrigger(t));
  ScriptApp.newTrigger('rentReminderRun').timeBased().everyMinutes(5).create();
}
