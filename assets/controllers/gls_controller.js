import { Controller } from '@hotwired/stimulus'
import CanvasDrawer from "../gls/infrastructure/canvas-drawer.js";
import Gls from "../gls/gls/gls.js";

/*
* The following line makes this controller "lazy": it won't be downloaded until needed
* See https://github.com/symfony/stimulus-bridge#lazy-controllers
*/
/* stimulusFetch: 'lazy' */
export default class extends Controller {
    static targets = ['canvas']

    gls = null

    connect() {
        this.canvasTarget.getContext('2d').scale(4,2)
        this.canvasTarget.getContext('2d').translate(-110, -40)

        fetch(this.element.dataset.glsData)
            .then(response => response.json())
            .then(data => this.render(data))
    }

    render(glsData) {
        const drawer = new CanvasDrawer(this.canvasTarget)
        this.gls = new Gls(glsData, drawer)
        window.requestAnimationFrame(() => this.animate())
    }

    animate() {
        this.gls.step()
        window.requestAnimationFrame(() => this.animate())
    }
}
