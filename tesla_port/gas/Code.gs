function setupTeslaPortTrigger() {
  ScriptApp.getProjectTriggers().forEach(t => { if (t.getHandlerFunction() === 'checkTeslaPort') ScriptApp.deleteTrigger(t); });
  ScriptApp.newTrigger('checkTeslaPort').timeBased().atHour(9).everyDays(1).inTimezone('Asia/Seoul').create();
}
function checkTeslaPort() {
  const url = 'https://YOURDOMAIN/tesla_port/cron/check.php?key=CHANGE_TO_LONG_RANDOM_STRING';
  const res = UrlFetchApp.fetch(url, {muteHttpExceptions:true, followRedirects:true});
  console.log(res.getResponseCode(), res.getContentText());
}
