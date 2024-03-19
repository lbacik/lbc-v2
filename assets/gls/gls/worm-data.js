import LineTools from "./line-tools.js";

class WormData {
  constructor(data) {
    this.data = WormData.validate(data)
  }

  getValue(key) {
    return this.data[key] || undefined
  }

  getData() {
    return this.data
  }

  static validate(data) {
    // TODO
    return data
  }

  getNextPoint(i) {
    const path = this.data.path
    let index

    if (this.data.direction === true) {
      index = (i + 1) % path.length
    } else {
      index = Math.abs(path.length - (i + 1)) % path.length
    }

    return path[index]
  }

  getLineThatHeadLiesOn(head) {
    let point = this.data.path[0]
    let next

    for (let i = 0; i <= this.data.path.length; i += 1) {
      next = this.getNextPoint(i)

      if (head[0] === point[0]) {
        if (LineTools.pointOnLine(point[1], next[1], head[1])) {
          return [point, next]
        }
      } else if (head[1] === point[1]) {
        if (LineTools.pointOnLine(point[0], next[0], head[0])) {
          return [point, next]
        }
      }

      point = next;
    }

    return undefined
  }

  getLineWhichStartsOn(point) {
    let start = this.data.path[0]
    let next

    for (let i = 0; i <= this.data.path.length; i += 1) {
      next = this.getNextPoint(i)

      if (start[0] === point[0] && start[1] === point[1]) {
        return [start, next]
      }

      start = next;
    }

    return undefined
  }

  getLineWhichEndsOn(point) {
    let start = this.data.path[0]
    let next

    for (let i = 0; i <= this.data.path.length; i += 1) {
      next = this.getNextPoint(i)

      if (next[0] === point[0] && next[1] === point[1]) {
        return [start, next]
      }

      start = next;
    }

    return undefined
  }
}

// module.exports = WormData

export default WormData
