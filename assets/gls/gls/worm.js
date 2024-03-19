import WormData from "./worm-data.js";
import LineTools from "./line-tools.js";

class Worm {
  constructor(data, drawer) {
    this.data = new WormData(data)
    this.head = this.data.getValue('head')
    this.drawer = drawer
  }

  data(data = null) {
    if (data !== null) {
      this.data = new WormData(data)
    }
    return this.data.getData()
  }

  drawWormLine(startPoint, line, length) {
    const tailDestinationPoint = LineTools.pointOnOppositeEnd(line)
    const dimensionIndex = LineTools.getDimensionIndex(line)
    let lineLength = Math.abs(tailDestinationPoint[dimensionIndex] - startPoint[dimensionIndex])

    if (length < lineLength) {
      if (tailDestinationPoint[dimensionIndex] < startPoint[dimensionIndex]) {
        tailDestinationPoint[dimensionIndex] = startPoint[dimensionIndex] - length
      } else {
        tailDestinationPoint[dimensionIndex] = startPoint[dimensionIndex] + length
      }
      lineLength = length
    }

    this.drawer.drawLine(
      startPoint,
      tailDestinationPoint,
      this.data.getValue('color'),
      this.data.getValue('width'),
    )

    return length - lineLength
  }

  drawWorm() {
    if (!('visible' in this.data.getData()) || this.data.getValue('visible') === true) {
      let line
      let startPoint = this.head
      let length = this.data.getValue('length')

      if (this.data.getValue('drawHead') === true) {
        this.drawer.drawPoint(this.head, this.data.getValue('color'), this.data.getValue('width'))
      }

      line = this.data.getLineThatHeadLiesOn(startPoint)

      while ((length = this.drawWormLine(startPoint, line, length)) > 0) {
        startPoint = LineTools.pointOnOppositeEnd(line)
        line = this.data.getLineWhichEndsOn(startPoint)
      }
    }
  }

  step() {
    this.getNewHeadPoint()
  }

  newHeadCalculation(line, step) {
    const dest = line[1]
    const dimensionIndex = LineTools.getDimensionIndex(line)
    let lineLength = Math.abs(dest[dimensionIndex] - this.head[dimensionIndex])

    if (step <= lineLength) {
      if (dest[dimensionIndex] < this.head[dimensionIndex]) {
        this.head[dimensionIndex] = this.head[dimensionIndex] - step
      } else {
        this.head[dimensionIndex] = this.head[dimensionIndex] + step
      }
      lineLength = step
    } else {
      this.head[dimensionIndex] = dest[dimensionIndex]
    }
    return step - lineLength
  }

  getNewHeadPoint() {
    let line = this.data.getLineThatHeadLiesOn(this.head)
    let i = 1
    let last = false
    let s = this.data.getValue('step')

    while (last === false) {
      s = this.newHeadCalculation(line, s)

      if (s === 0) {
        last = true
      } else {
        line = this.data.getLineWhichStartsOn(line[1])
      }

      if (++i > 10) {
        break
      }
    }
  }

  drawPathPoints() {
    if (this.data.getValue('pathPoints') === true) {
      const path = this.data.getValue('path')
      for (let i = 0; i < path.length; i += 1) {
        this.drawer.drawPoint(path[i], this.data.getValue('pathPointsColor') || 'red')
      }
    }
  }
}

// module.exports = Worm

export default Worm
