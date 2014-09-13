<?php if ($poem) { ?>
    <div class="columns small-12 small-centered medium-7 medium-centered large-5 large-centered">
        <div class="row">
            <div class="form-action columns small-6">
                <div class="flex-button">
                    <?php if ($mode === 'nyeste') { ?>
                        <a class="button" href="{{ URL::route('randomPoem') }}/nyeste">Nyeste</a>
                    <?php } else { ?>
                        <a class="button" href="{{ URL::route('randomPoem') }}">Les et til</a>
                    <?php } ?>
                    <span class="split-button-icon" data-dropdown="drop"></span>
                </div>
                <ul id="drop" class="f-dropdown" data-dropdown-content>
                    <li><a href="{{ URL::route('randomPoem') }}">Alle</a></li>
                    <li><a href="{{ URL::route('randomPoem') }}/nyeste">Nyeste</a></li>
                </ul>
            </div>
            <p class="form-action columns small-6">
                <a class="button" href="{{ URL::route('frontpage') }}">Skriv</a>
            </p>
        </div>
        <article class="poem">
            <h1 class="poem-title">
                <?php echo $poem['title']; ?>
            </h1>
            <div class="poem-text">
                <?php echo $poem['text']; ?>
            </div>
        </article>
    </div>
<?php } ?>
