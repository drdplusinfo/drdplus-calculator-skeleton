<div class="row">
  <form class="col delete" action="/" method="post" onsubmit="return window.confirm('Opravdu smazat včetně historie?')">
    <label>
      <input type="submit" value="Smazat" name="<?= $controller::DELETE_HISTORY ?>" class="manual btn-danger">
      <span class="hint">(včetně dlouhodobé paměti)</span>
    </label>
  </form>
</div>
