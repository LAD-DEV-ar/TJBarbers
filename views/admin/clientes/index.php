<main class="admin-page container">
    <header class="admin-header">
        <div class="admin-header__left">
            <h1>Tus Clientes</h1>
            <p class="muted">Aqui tienes un listado de todos los clientes <br>que estan vinculado a tu tarjeta</p>
        </div>

        <nav class="admin-header__right" aria-label="acciones admin">
            <a class="btn btn--ghost" href="/administrar-tarjeta">Mi tarjeta</a>
            <a class="btn btn--ghost" href="/admin/recompensas">Recompensas</a>
            <a class="btn btn--ghost" href="/admin/solicitudes">Solicitudes</a>
            <a class="btn btn--ghost" href="/admin/clientes">Clientes</a>
            <a class="btn btn--ghost" href="/logout">Cerrar sesión</a>
        </nav>
    </header>

    <div class="card">
        <div class="table-wrap" aria-live="polite">
            <table class="table" role="table" aria-label="Solicitudes">
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Casilla Actual</th>
                        <th>Racha</th>
                        <th>Accion</th>
                    </tr>
                </thead>
                    <tbody>
                    <?php foreach($clientes as $c):?>
                        <tr>
                            <td data-label="Nombre"><?= htmlspecialchars($c->nombre) ?></td>
                            <td data-label="Casilla Actual"><?= htmlspecialchars($c->casilla_actual) ?></td>
                            <td data-label="Racha"><?= htmlspecialchars($c->racha) ?></td>
                            <td data-label="Reinicio">
                                <form method="POST" action="/admin/clientes/reinicio" style="display:inline">
                                    <input type="hidden" name="id_tarjeta" value="<?= $c->id_tarjeta ?>"> 
                                    <input type="hidden" name="id" value="<?= $c->id ?>"> 
                                    <input type="hidden" name="casilla_actual" value="<?= $c->casilla_actual ?>"> 
                                    <input type="hidden" name="racha" value="<?= $c->racha ?>"> 
                                    <button type="submit" class="btn btn--primary">Reiniciar</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach;?> 
                </tbody>
            </table>
        </div>
    </div>
</main>